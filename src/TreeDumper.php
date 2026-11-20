<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

use Medas\Core\Attributes\Service;

/**
 * Writes a token tree as plain text: one line per statement with its block depth and type, and below it one line per
 * token with its text, its token name and the flags the tokenizer set on it. Used for snapshot tests, where a change in
 * the tokenizer shows up as a readable diff.
 *
 * Statement lines end with the type of their block in brackets, like `[ClassLike]`.
 *
 * Token flags: `@<Context>` the context, `A` in an attribute, `]A` closes an attribute, `S` in a string, `T` in a
 * type declaration, `b<n>` the bracket depth, `#<n>` the number of a bracket pair (the same on both brackets),
 * `opens:<BlockType>` opens a block, `closes` closes a block, `nl:<n>` and `sp:<n>` the original line breaks and spaces
 * after the token.
 *
 * @see BlockDumper for a coloured version on the console
 */
#[Service]
readonly class TreeDumper
{
    public function __construct(
        private StatementTypeFinder $typeFinder,
    )
    {
    }

    public function dump(TokenTree $tree): string
    {
        $lines = [];
        $pairs = [];

        foreach ($tree->statements() as $statement) {
            $lines[] = $this->statementLine($statement);

            foreach ($statement as $token) {
                $lines[] = $this->tokenLine($token, $pairs);
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function statementLine(Statement $statement): string
    {
        return sprintf(
            'd%d %s [%s]',
            $statement->block->depth,
            $this->shortName($this->typeFinder->for($statement)),
            $statement->block->type->name
        );
    }

    /**
     * @param array<int, int> $pairs The number of each bracket pair, by the object id of its opening bracket
     */
    private function tokenLine(Token $token, array &$pairs): string
    {
        $text = $this->escape($token->text);
        $line = '    ' . $text;
        $name = $token->getTokenName();

        if ($name !== $token->text) {
            $line .= '  ' . $name;
        }

        $flags = $this->flags($token, $pairs);

        if ($flags) {
            $line .= '  | ' . implode(' ', $flags);
        }

        return $line;
    }

    /**
     * @return string[]
     */
    private function flags(Token $token, array &$pairs): array
    {
        $flags = [];

        if ($token->context !== null) {
            $flags[] = '@' . $this->shortName($token->context);
        }

        if ($token->inAttribute) {
            $flags[] = 'A';
        }

        if ($token->closesAttribute) {
            $flags[] = ']A';
        }

        if ($token->inString) {
            $flags[] = 'S';
        }

        if ($token->inTypeDeclaration) {
            $flags[] = 'T';
        }

        if ($token->bracketDepth > 0) {
            $flags[] = 'b' . $token->bracketDepth;
        }

        if ($token->matchingBracket !== null) {
            $opener = $pairs[spl_object_id($token->matchingBracket)] ?? null;

            if ($opener === null) {
                $pairs[spl_object_id($token)] = count($pairs) + 1;
            }

            $flags[] = '#' . ($opener ?? $pairs[spl_object_id($token)]);
        }

        if ($token->opensBlock !== null) {
            $flags[] = 'opens:' . $token->opensBlock->name;
        }

        if ($token->closesBlock) {
            $flags[] = 'closes';
        }

        if ($token->originalLineBreaksAfter > 0) {
            $flags[] = 'nl:' . $token->originalLineBreaksAfter;
        }
        elseif ($token->originalSpacesAfter > 0) {
            $flags[] = 'sp:' . $token->originalSpacesAfter;
        }

        return $flags;
    }

    private function shortName(object $object): string
    {
        return (new \ReflectionClass($object))->getShortName();
    }

    private function escape(string $text): string
    {
        return strtr($text, ["\r" => '\r', "\n" => '\n', "\t" => '\t']);
    }
}
