<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\Passes;

use Medas\Core\Attributes\Service;
use Medas\PhpTokenizer\{BlockType, Token};

/**
 * Sets ```isClassReference``` on names that refer to a class, interface, trait or enum: in types, after new and
 * instanceof, before ::, in extends, implements and trait use lists, after insteadof, and as attribute names.
 *
 * Built-in types and self, static and parent are not class references.
 */
#[Service]
readonly class ClassReferenceMarker implements TokenizerPass
{
    private const array NAMES = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE];

    private const array NOT_CLASSES = [
        'array',
        'bool',
        'callable',
        'false',
        'float',
        'int',
        'iterable',
        'mixed',
        'never',
        'null',
        'object',
        'parent',
        'self',
        'static',
        'string',
        'true',
        'void',
    ];

    private const array MEMBER_ACCESS = [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON];

    public function runsAfter(): array
    {
        return [TypeDeclarationMarker::class];
    }

    public function run(TokenizerJob $job): void
    {
        foreach ($job->tokens as $token) {
            if ($token->is(self::NAMES) && !in_array(strtolower($token->text), self::NOT_CLASSES, true)) {
                $token->isClassReference = $this->isClassReference($token);
            }
        }
    }

    private function isClassReference(Token $token): bool
    {
        $previous = $token->previous;

        if ($token->typeDeclaration !== null) {
            return true;
        }

        if ($previous?->is([T_NEW, T_INSTANCEOF, T_INSTEADOF])) {
            return true;
        }

        if ($token->next?->is(T_DOUBLE_COLON) && !$previous?->is(self::MEMBER_ACCESS)) {
            return true;
        }

        if ($this->isInList($token, [T_EXTENDS, T_IMPLEMENTS, T_INSTEADOF])) {
            return true;
        }

        if ($this->isTraitUse($token)) {
            return true;
        }

        return $this->isAttributeName($token);
    }

    /**
     * Whether the name is in a comma separated list after one of the given keywords.
     */
    private function isInList(Token $token, array $keywords): bool
    {
        $previous = $token->previous;

        while ($previous?->is(T_COMMA) && $previous->previous?->is(self::NAMES)) {
            $previous = $previous->previous->previous;
        }

        return $previous?->is($keywords) ?? false;
    }

    /**
     * "use A, B;" or "use A, B {" in the body of a class.
     */
    private function isTraitUse(Token $token): bool
    {
        if ($token->block->type !== BlockType::ClassLike) {
            return false;
        }

        $first = $token->statement->firstNonCommentToken();

        return $first?->is(T_USE) && $this->isInList($token, [T_USE]);
    }

    /**
     * #[Name(...), Other] — a name right after #[, or after a comma between attributes.
     */
    private function isAttributeName(Token $token): bool
    {
        if (!$token->inAttribute) {
            return false;
        }

        $previous = $token->previous;

        if ($previous?->is(T_ATTRIBUTE)) {
            return true;
        }

        if (!$previous?->is(T_COMMA)) {
            return false;
        }

        // The comma must be between attributes, not between arguments
        for ($before = $previous->previous; $before !== null; $before = $before->previous) {
            if ($before->is(T_ATTRIBUTE)) {
                return $before->bracketDepth + 1 === $previous->bracketDepth;
            }

            if ($before->matchingBracket !== null && $before->is([T_ROUND_BRACKET_CLOSE, T_SQUARE_BRACKET_CLOSE])) {
                $before = $before->matchingBracket;
            }
        }

        return false;
    }
}
