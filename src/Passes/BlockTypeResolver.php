<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\Passes;

use Medas\Core\Attributes\Service;
use Medas\PhpTokenizer\{BlockType, Token};

/**
 * Decides for every { whether it opens a block and what kind of block (```opensBlock```), and marks the } that closes
 * it (```closesBlock```). Braces after ->, ?->, ::, $ and \ are part of an expression or a group import, not blocks.
 *
 * Alternative syntax is handled the same way: the : after if (...), else, while (...) etc. opens a block, and endif,
 * endwhile etc., or the next else or elseif, closes it.
 *
 * Also sets ```bracketDepth```, which needs to know which braces are blocks.
 */
#[Service]
readonly class BlockTypeResolver implements TokenizerPass
{
    private const array NON_BLOCK_PREFIXES = [
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
        T_DOLLAR,
        T_NS_SEPARATOR,
        T_BACKSLASH,

        // Kept from the time of $string{0}, removed in PHP 8.0
        T_SQUARE_BRACKET_CLOSE,
    ];

    private const array CONTROL_KEYWORDS = [T_IF, T_ELSEIF, T_WHILE, T_FOR, T_FOREACH, T_CATCH, T_DECLARE];

    private const array ALTERNATIVE_SYNTAX_OPENERS = [T_IF, T_ELSEIF, T_WHILE, T_FOR, T_FOREACH, T_DECLARE];

    private const array ALTERNATIVE_SYNTAX_ENDS = [
        T_ENDIF,
        T_ENDWHILE,
        T_ENDFOR,
        T_ENDFOREACH,
        T_ENDSWITCH,
        T_ENDDECLARE,
    ];

    private const array STRUCTURE_KEYWORDS = [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];

    private const array TYPE_TOKENS = [
        T_STRING,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_RELATIVE,
        T_ARRAY,
        T_CALLABLE,
        T_STATIC,
        T_QUESTION_MARK,
        T_VERTICAL_BAR,
        T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
        T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
    ];

    private const array CLASS_HEADER_TOKENS = [
        T_STRING,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_RELATIVE,
        T_COMMA,
        T_COLON,
        T_EXTENDS,
        T_IMPLEMENTS,
        T_ARRAY,
    ];

    public function runsAfter(): array
    {
        return [BracketMatcher::class];
    }

    public function run(TokenizerJob $job): void
    {
        // The open blocks, innermost last: [BlockType, bool $isAlternativeSyntax]
        $blocks = [[BlockType::File, false]];

        // The open brackets that are not blocks, innermost last
        $brackets = [];

        foreach ($job->tokens as $token) {
            if ($this->closesAlternativeSyntax($token) && end($blocks)[1]) {
                $token->closesBlock = true;
                array_pop($blocks);
            }

            if ($token->is(T_CURLY_BRACKET_CLOSE) && $token->matchingBracket?->opensBlock !== null) {
                $token->closesBlock = true;
                array_pop($blocks);
            }
            elseif ($token->is(T_ROUND_BRACKET_CLOSE) || $token->is(T_SQUARE_BRACKET_CLOSE)
                    || $token->is(T_CURLY_BRACKET_CLOSE)) {
                array_pop($brackets);
            }

            $token->bracketDepth = count($brackets);

            if ($token->is(T_CURLY_BRACKET_OPEN) && !$token->inString) {
                $token->opensBlock = $this->braceType($token, end($blocks)[0], end($brackets) ?: null);
            }
            elseif ($token->is(T_COLON)) {
                $token->opensBlock = $this->alternativeSyntaxType($token);
            }

            if ($token->opensBlock !== null) {
                $blocks[] = [$token->opensBlock, $token->is(T_COLON)];
            }
            elseif ($token->is([
                T_ROUND_BRACKET_OPEN,
                T_SQUARE_BRACKET_OPEN,
                T_CURLY_BRACKET_OPEN,
                T_CURLY_OPEN,
                T_DOLLAR_OPEN_CURLY_BRACES,
                T_ATTRIBUTE,
            ])) {
                $brackets[] = $token;
            }
        }
    }

    private function braceType(Token $brace, BlockType $enclosing, Token|null $enclosingBracket): BlockType|null
    {
        // A comment before the brace, like in "function f(): void // Comment", says nothing about what it opens
        $previous = $this->codeBefore($brace);

        if ($previous === null) {
            return BlockType::Plain;
        }

        if ($previous->is(self::NON_BLOCK_PREFIXES)) {
            return null;
        }

        if ($previous->is([T_TRY, T_ELSE, T_FINALLY, T_DO])) {
            return BlockType::Control;
        }

        $beforeTypes = $this->skipBackOverReturnType($previous);

        if ($beforeTypes !== $previous
                && $beforeTypes?->is(T_COLON)
                && $beforeTypes->previous?->is(T_ROUND_BRACKET_CLOSE)) {
            // ") : type {", the body of a function or closure with a return type
            $previous = $beforeTypes->previous;
        }

        if ($previous->is(T_ROUND_BRACKET_CLOSE)) {
            $type = $this->typeAfterParentheses($previous, $enclosing);

            if ($type !== null) {
                return $type;
            }
        }

        if ($enclosing === BlockType::HookList) {
            // get { ... }, &get { ... }
            return BlockType::HookBody;
        }

        if ($this->isClassHeader($previous)) {
            return BlockType::ClassLike;
        }

        if ($previous->is(T_NAMESPACE) || $previous->previous?->is(T_NAMESPACE)) {
            return BlockType::Namespace;
        }

        if ($enclosing === BlockType::ClassLike && $this->isTraitUse($previous)) {
            return BlockType::TraitAdaptation;
        }

        if ($this->isPropertyWithHooks($previous, $enclosing, $enclosingBracket)) {
            return BlockType::HookList;
        }

        return BlockType::Plain;
    }

    /**
     * The block type of "... (...) {", by what comes before the parentheses.
     */
    private function typeAfterParentheses(Token $closer, BlockType $enclosing): BlockType|null
    {
        $keyword = $closer->matchingBracket?->previous;

        if ($keyword === null) {
            return null;
        }

        if ($keyword->is(T_SWITCH)) {
            return BlockType::Switch;
        }

        if ($keyword->is(T_MATCH)) {
            return BlockType::Match;
        }

        if ($keyword->is(self::CONTROL_KEYWORDS)) {
            return BlockType::Control;
        }

        if ($keyword->is(T_USE) && $keyword->previous?->is(T_ROUND_BRACKET_CLOSE)) {
            // function (...) use (...) {
            return $this->typeAfterParentheses($keyword->previous, $enclosing);
        }

        if ($keyword->is([T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG, T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG])) {
            // function &(...) {
            $keyword = $keyword->previous;
        }

        if ($keyword?->is([T_FUNCTION, T_FN])) {
            return BlockType::Closure;
        }

        if ($keyword?->is(T_CLASS)) {
            // new class (...) {
            return BlockType::ClassLike;
        }

        $beforeName = $keyword?->previous;

        if ($beforeName?->is([T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG, T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG])) {
            $beforeName = $beforeName->previous;
        }

        if ($beforeName?->is(T_FUNCTION)) {
            return BlockType::Function;
        }

        if ($enclosing === BlockType::HookList) {
            // set (...) { ... }
            return BlockType::HookBody;
        }

        return null;
    }

    /**
     * Walks back from the token before a { over a return type, including DNF groups like (A&B)|null.
     *
     * @return Token|null The first token before the type
     */
    private function skipBackOverReturnType(Token $token): Token|null
    {
        while ($token !== null) {
            if ($token->is(self::TYPE_TOKENS)) {
                $token = $token->previous;
            }
            elseif ($token->is(T_ROUND_BRACKET_CLOSE) && $token->matchingBracket?->previous?->is([
                T_VERTICAL_BAR,
                T_COLON,
            ])) {
                // A DNF group
                $token = $token->matchingBracket->previous;
            }
            else {
                return $token;
            }
        }

        return null;
    }

    /**
     * The token before this one, skipping comments.
     */
    private function codeBefore(Token $token): Token|null
    {
        $previous = $token->previous;

        while ($previous?->is([T_COMMENT, T_DOC_COMMENT])) {
            $previous = $previous->previous;
        }

        return $previous;
    }

    private function isClassHeader(Token $token): bool
    {
        while ($token !== null) {
            if ($token->is(self::STRUCTURE_KEYWORDS)) {
                return !$token->previous?->is(T_DOUBLE_COLON);
            }

            if ($token->is(T_ROUND_BRACKET_CLOSE)) {
                // The arguments of new class (...)
                $token = $token->matchingBracket?->previous;
            }
            elseif ($token->is(self::CLASS_HEADER_TOKENS)) {
                $token = $this->codeBefore($token);
            }
            else {
                return false;
            }
        }

        return false;
    }

    private function isTraitUse(Token $token): bool
    {
        while ($token?->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE, T_COMMA])) {
            $token = $token->previous;
        }

        return $token?->is(T_USE) ?? false;
    }

    /**
     * "$name {" in a class body or in the parameters of a constructor, possibly with a default value in between.
     */
    private function isPropertyWithHooks(Token $token, BlockType $enclosing, Token|null $enclosingBracket): bool
    {
        $inParameters = $enclosingBracket?->is(T_ROUND_BRACKET_OPEN) ?? false;

        if ($enclosingBracket !== null && !$inParameters) {
            return false;
        }

        if (!$inParameters && $enclosing !== BlockType::ClassLike) {
            return false;
        }

        while ($token !== null && !$token->is([T_SEMICOLON, T_CURLY_BRACKET_OPEN, T_CURLY_BRACKET_CLOSE, T_COMMA])) {
            if ($token->is(T_VARIABLE)) {
                return true;
            }

            if ($token->is([T_FUNCTION, T_FN]) || $token === $enclosingBracket) {
                return false;
            }

            $token = $token->is([T_ROUND_BRACKET_CLOSE, T_SQUARE_BRACKET_CLOSE])
                ? $token->matchingBracket?->previous
                : $token->previous;
        }

        return false;
    }

    /**
     * The : of "if (...):", "elseif (...):", "else:", "while (...):", "for (...):", "foreach (...):",
     * "switch (...):" and "declare (...):".
     */
    private function alternativeSyntaxType(Token $colon): BlockType|null
    {
        $previous = $colon->previous;

        if ($previous?->is(T_ELSE)) {
            return BlockType::Control;
        }

        if (!$previous?->is(T_ROUND_BRACKET_CLOSE)) {
            return null;
        }

        $keyword = $previous->matchingBracket?->previous;

        if ($keyword?->is(T_SWITCH)) {
            return BlockType::Switch;
        }

        return $keyword?->is(self::ALTERNATIVE_SYNTAX_OPENERS) ? BlockType::Control : null;
    }

    /**
     * endif, endwhile, endfor, endforeach, endswitch, enddeclare, and else: and elseif (...): after an alternative
     * syntax block.
     */
    private function closesAlternativeSyntax(Token $token): bool
    {
        if ($token->is(self::ALTERNATIVE_SYNTAX_ENDS)) {
            return true;
        }

        if ($token->is(T_ELSE)) {
            return $token->next?->is(T_COLON) ?? false;
        }

        if ($token->is(T_ELSEIF)) {
            return $token->next?->matchingBracket?->next?->is(T_COLON) ?? false;
        }

        return false;
    }
}
