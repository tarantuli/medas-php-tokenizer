<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\Passes;

use Medas\Core\Attributes\Service;
use Medas\PhpTokenizer\{BlockType, Declaration, DeclarationKind, Statement, Token};

/**
 * Sets ```Statement::$declaration``` and ```Statement::$attributes``` on statements that declare a class, interface,
 * trait, enum, function, method, property, constant or enum case, or that use a trait.
 */
#[Service]
readonly class DeclarationReader implements TokenizerPass
{
    private const array MODIFIERS = [
        T_ABSTRACT,
        T_FINAL,
        T_READONLY,
        T_PUBLIC,
        T_PROTECTED,
        T_PRIVATE,
        T_PUBLIC_SET,
        T_PROTECTED_SET,
        T_PRIVATE_SET,
        T_STATIC,
        T_VAR,
    ];

    private const array STRUCTURES = [
        T_CLASS => DeclarationKind::Class_,
        T_INTERFACE => DeclarationKind::Interface,
        T_TRAIT => DeclarationKind::Trait,
        T_ENUM => DeclarationKind::Enum,
    ];

    public function runsAfter(): array
    {
        return [TypeDeclarationMarker::class];
    }

    public function run(TokenizerJob $job): void
    {
        foreach ($job->tree->statements() as $statement) {
            $this->read($statement);
        }
    }

    private function read(Statement $statement): void
    {
        $token = $statement->firstToken();
        $attributes = [];

        while ($token?->is([T_COMMENT, T_DOC_COMMENT, T_ATTRIBUTE])) {
            if ($token->is(T_ATTRIBUTE)) {
                $attributes[] = $token;
                $token = $token->matchingBracket;
            }

            $token = $token?->next;
        }

        $modifiers = [];

        while ($token?->is(self::MODIFIERS) && $token->statement === $statement) {
            $modifiers[] = strtolower($token->text);
            $token = $token->next;
        }

        if ($token === null || $token->statement !== $statement) {
            return;
        }

        $declaration = $this->declaration($statement, $token, $modifiers);

        if ($declaration !== null) {
            $statement->declaration = $declaration;
            $statement->attributes = $attributes;
        }
    }

    /**
     * @param string[] $modifiers
     */
    private function declaration(Statement $statement, Token $keyword, array $modifiers): Declaration|null
    {
        $inClass = $statement->block->type === BlockType::ClassLike;

        if ($keyword->is(array_keys(self::STRUCTURES)) && !$keyword->next?->is(T_ROUND_BRACKET_OPEN)) {
            $kind = self::STRUCTURES[$keyword->id];

            return new Declaration($kind, $keyword->next?->text, $modifiers, $keyword);
        }

        if ($keyword->is(T_FUNCTION)) {
            $name = $keyword->next?->is([
                T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
                T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
            ]) ? $keyword->next->next : $keyword->next;

            if ($name === null || $name->is(T_ROUND_BRACKET_OPEN)) {
                // A closure, not a declaration
                return null;
            }

            $kind = $inClass ? DeclarationKind::Method : DeclarationKind::Function;

            return new Declaration($kind, $name->text, $modifiers, $keyword);
        }

        if ($keyword->is(T_CONST)) {
            return new Declaration(DeclarationKind::Constant, $this->constantName($keyword), $modifiers, $keyword);
        }

        if (!$inClass) {
            return null;
        }

        if ($keyword->is(T_CASE)) {
            return new Declaration(DeclarationKind::EnumCase, $keyword->next?->text, $modifiers, $keyword);
        }

        if ($keyword->is(T_USE)) {
            return new Declaration(DeclarationKind::TraitUse, null, $modifiers, $keyword);
        }

        if ($modifiers !== []) {
            // A property: [type] $name
            for ($token = $keyword; $token !== null && $token->statement === $statement; $token = $token->next) {
                if ($token->is(T_VARIABLE)) {
                    return new Declaration(DeclarationKind::Property, $token->text, $modifiers, $keyword);
                }

                if ($token->typeDeclaration === null) {
                    break;
                }
            }
        }

        return null;
    }

    /**
     * In "const int A = 1", the name is the token before the first =.
     */
    private function constantName(Token $const): string|null
    {
        for ($token = $const->next; $token !== null; $token = $token->next) {
            if ($token->is(T_ASSIGNMENT)) {
                return $token->previous->text;
            }

            if ($token->is(T_SEMICOLON)) {
                return null;
            }
        }

        return null;
    }
}
