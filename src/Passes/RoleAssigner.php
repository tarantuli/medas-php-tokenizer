<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\Passes;

use Medas\Core\Attributes\Service;
use Medas\PhpTokenizer\{BlockType, Token, TokenRole};

/**
 * Sets ```role``` on ?, :, & and ..., which each mean different things depending on where they are.
 */
#[Service]
readonly class RoleAssigner implements TokenizerPass
{
    private const array BY_REFERENCE_PREFIXES = [
        T_ROUND_BRACKET_OPEN,
        T_SQUARE_BRACKET_OPEN,
        T_COMMA,
        T_ASSIGNMENT,
        T_AS,
        T_DOUBLE_ARROW,
        T_FUNCTION,
        T_FN,
    ];

    public function runsAfter(): array
    {
        return [TypeDeclarationMarker::class];
    }

    public function run(TokenizerJob $job): void
    {
        // The bracket depths of the ternaries waiting for their :, innermost last
        $openTernaries = [];

        // The open brackets, innermost last
        $brackets = [];

        foreach ($job->tokens as $token) {
            if ($token->is([T_ROUND_BRACKET_CLOSE, T_SQUARE_BRACKET_CLOSE, T_CURLY_BRACKET_CLOSE])) {
                array_pop($brackets);
            }

            if ($token->is(T_SEMICOLON)) {
                $openTernaries = [];
            }

            if ($token->is(T_QUESTION_MARK)) {
                $token->role = $this->questionMarkRole($token);

                if ($token->role === TokenRole::TernaryThen) {
                    $openTernaries[] = $token->bracketDepth;
                }
            }
            elseif ($token->is(T_COLON)) {
                $token->role = $this->colonRole($token, $openTernaries);
            }
            elseif ($token->is([
                T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
                T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
            ])) {
                $token->role = $this->ampersandRole($token);
            }
            elseif ($token->is(T_ELLIPSIS)) {
                $token->role = $this->ellipsisRole($token, end($brackets) ?: null);
            }

            if ($token->matchingBracket !== null && !$token->is([
                T_ROUND_BRACKET_CLOSE,
                T_SQUARE_BRACKET_CLOSE,
                T_CURLY_BRACKET_CLOSE,
            ])) {
                $brackets[] = $token;
            }
        }
    }

    private function questionMarkRole(Token $token): TokenRole
    {
        if ($token->typeDeclaration !== null) {
            return TokenRole::NullableMarker;
        }

        if ($token->next?->is(T_COLON)) {
            return TokenRole::ShortTernary;
        }

        return TokenRole::TernaryThen;
    }

    /**
     * @param int[] $openTernaries
     */
    private function colonRole(Token $colon, array &$openTernaries): TokenRole|null
    {
        $previous = $colon->previous;

        if ($colon->opensBlock !== null) {
            return TokenRole::AlternativeSyntaxColon;
        }

        if (Signatures::isReturnTypeColon($colon)) {
            return TokenRole::ReturnTypeColon;
        }

        if ($previous?->role === TokenRole::ShortTernary) {
            return TokenRole::ShortTernary;
        }

        if ($previous?->previous?->is(T_ENUM)) {
            return TokenRole::EnumBackingColon;
        }

        if ($openTernaries !== [] && end($openTernaries) === $colon->bracketDepth) {
            array_pop($openTernaries);

            return TokenRole::TernaryElse;
        }

        if ($this->isCaseColon($colon)) {
            return TokenRole::CaseColon;
        }

        if ($previous !== null && preg_match('/^\w+$/', $previous->text)) {
            if ($previous->previous?->is([T_ROUND_BRACKET_OPEN, T_COMMA])) {
                return TokenRole::NamedArgumentColon;
            }

            if ($previous === $previous->statement->firstNonCommentToken()) {
                return TokenRole::GotoLabelColon;
            }
        }

        return null;
    }

    private function isCaseColon(Token $colon): bool
    {
        return $colon->block->type === BlockType::Switch
            && $colon === $colon->statement->lastToken()
            && $colon->statement->firstNonCommentToken()?->is([T_CASE, T_DEFAULT]);
    }

    private function ampersandRole(Token $ampersand): TokenRole|null
    {
        if ($ampersand->typeDeclaration !== null) {
            // The & of an intersection type
            return null;
        }

        $previous = $ampersand->previous;

        if ($previous?->is(self::BY_REFERENCE_PREFIXES) || $previous?->typeDeclaration !== null) {
            return TokenRole::ByReference;
        }

        return null;
    }

    private function ellipsisRole(Token $ellipsis, Token|null $enclosingBracket): TokenRole
    {
        if ($enclosingBracket !== null && Signatures::isParameterList($enclosingBracket)) {
            return TokenRole::Variadic;
        }

        if ($ellipsis->previous?->is(T_ROUND_BRACKET_OPEN) && $ellipsis->next?->is(T_ROUND_BRACKET_CLOSE)) {
            return TokenRole::FirstClassCallable;
        }

        return TokenRole::Spread;
    }
}
