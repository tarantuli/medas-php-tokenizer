<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\Passes;

use Medas\Core\Attributes\Service;
use Medas\PhpTokenizer\{BlockType, Token, TokenType, TypeDeclarationKind};

/**
 * Sets ```typeDeclaration``` on every token of a type: the names, ?, |, the & of an intersection, and the
 * parentheses of a DNF group like (A&B)|null. Covers parameters, return types, properties, typed class constants
 * and catch.
 */
#[Service]
readonly class TypeDeclarationMarker implements TokenizerPass
{
    private const array TYPE_TOKENS = [
        T_STRING,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_RELATIVE,
        T_ARRAY,
        T_CALLABLE,
        T_STATIC,
        TokenType::QUESTION_MARK,
        TokenType::VERTICAL_BAR,
        T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
    ];

    private const array PROPERTY_MODIFIERS = [
        T_PUBLIC,
        T_PROTECTED,
        T_PRIVATE,
        T_PUBLIC_SET,
        T_PROTECTED_SET,
        T_PRIVATE_SET,
        T_STATIC,
        T_READONLY,
        T_VAR,
        T_FINAL,
        T_ABSTRACT,
    ];

    public function runsAfter(): array
    {
        return [StatementBuilder::class];
    }

    public function run(TokenizerJob $job): void
    {
        foreach ($job->tokens as $token) {
            if ($token->is(TokenType::ROUND_BRACKET_OPEN) && Signatures::isParameterList($token)) {
                $this->markParameters($token);
            }
            elseif ($token->is(TokenType::COLON) && Signatures::isReturnTypeColon($token)) {
                $this->markTypeFrom($token->next, TypeDeclarationKind::ReturnType);
            }
            elseif ($token->is(T_CATCH) && $token->next?->is(TokenType::ROUND_BRACKET_OPEN)) {
                $this->markTypeFrom($token->next->next, TypeDeclarationKind::Catch);
            }
        }

        foreach ($job->tree->statements() as $statement) {
            if ($statement->block->type === BlockType::ClassLike && $statement->firstToken() !== null) {
                $this->markClassMember($statement->firstToken());
            }
        }
    }

    /**
     * Each parameter is [attributes] [modifiers] [type] [&] [...] $name [= default] [hooks].
     */
    private function markParameters(Token $open): void
    {
        $beforeVariable = true;

        for ($token = $open->next; $token !== null && $token !== $open->matchingBracket; $token = $token->next) {
            if ($token->is(T_ATTRIBUTE)) {
                $token = $token->matchingBracket;
            }
            elseif ($beforeVariable) {
                if ($token->is(T_VARIABLE)) {
                    $beforeVariable = false;
                }
                elseif ($token->is(TokenType::ROUND_BRACKET_OPEN)) {
                    $this->markUpTo($token, $token->matchingBracket, TypeDeclarationKind::Parameter);
                    $token = $token->matchingBracket;
                }
                elseif ($token->is(self::TYPE_TOKENS)) {
                    $token->typeDeclaration = TypeDeclarationKind::Parameter;
                }
            }
            elseif ($token->is(TokenType::COMMA)) {
                $beforeVariable = true;
            }
            elseif ($token->matchingBracket !== null) {
                // A bracket in a default value, or the hooks of a promoted property
                $token = $token->matchingBracket;
            }

            if ($token === null) {
                return;
            }
        }
    }

    /**
     * Marks the type that starts at the given token, up to the first token that can't be part of a type.
     */
    private function markTypeFrom(Token|null $token, TypeDeclarationKind $kind): void
    {
        while ($token !== null) {
            if ($token->is(TokenType::ROUND_BRACKET_OPEN) && $token->matchingBracket !== null) {
                $this->markUpTo($token, $token->matchingBracket, $kind);
                $token = $token->matchingBracket->next;
            }
            elseif ($token->is(self::TYPE_TOKENS)) {
                $token->typeDeclaration = $kind;
                $token = $token->next;
            }
            else {
                return;
            }
        }
    }

    private function markUpTo(Token $from, Token $to, TypeDeclarationKind $kind): void
    {
        for ($token = $from; $token !== null; $token = $token->next) {
            $token->typeDeclaration = $kind;

            if ($token === $to) {
                return;
            }
        }
    }

    /**
     * "[modifiers] type $name" is a property, "[modifiers] const type NAME = ..." a typed constant.
     */
    private function markClassMember(Token $token): void
    {
        while ($token?->is([T_ATTRIBUTE, T_DOC_COMMENT, T_COMMENT])) {
            $token = $token->is(T_ATTRIBUTE) ? $token->matchingBracket?->next : $token->next;
        }

        $hasModifier = false;

        while ($token?->is(self::PROPERTY_MODIFIERS)) {
            $hasModifier = true;
            $token = $token->next;
        }

        if ($token?->is(T_CONST)) {
            $this->markConstantType($token->next);
        }
        elseif ($hasModifier && $token !== null && !$token->is([T_FUNCTION, T_VARIABLE])) {
            $this->markTypeFrom($token, TypeDeclarationKind::Property);
        }
    }

    /**
     * In "const int A = 1", everything between const and the name is the type.
     */
    private function markConstantType(Token|null $first): void
    {
        $tokens = [];

        for ($token = $first; $token !== null && !$token->is([TokenType::ASSIGNMENT, TokenType::SEMICOLON]); $token = $token->next) {
            $tokens[] = $token;
        }

        array_pop($tokens);

        foreach ($tokens as $token) {
            $token->typeDeclaration = TypeDeclarationKind::ClassConstant;
        }
    }
}
