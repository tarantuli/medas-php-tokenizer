<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

use Medas\Core\Attributes\Service;

/**
 * Writes a token tree as plain text: one line per statement with its block depth and type, and below it one line per
 * token with its text, its token name and the flags the tokenizer set on it. Used for snapshot tests, where a change in
 * the tokenizer shows up as a readable diff.
 *
 * Token flags: `@<Context>` the context, `A` in an attribute, `]A` closes an attribute, `S` in a string, `T` in a
 * type declaration.
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

        foreach ($tree->statements() as $statement) {
            $lines[] = $this->statementLine($statement);

            foreach ($statement as $token) {
                $lines[] = $this->tokenLine($token);
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function statementLine(Statement $statement): string
    {
        return sprintf(
            'd%d %s',
            $statement->block->depth,
            $this->shortName($this->typeFinder->for($statement))
        );
    }

    private function tokenLine(Token $token): string
    {
        $text = $this->escape($token->text);
        $line = '    ' . $text;
        $name = $token->getTokenName();

        if ($name !== $token->text) {
            $line .= '  ' . $name;
        }

        $flags = $this->flags($token);

        if ($flags) {
            $line .= '  | ' . implode(' ', $flags);
        }

        return $line;
    }

    /**
     * @return string[]
     */
    private function flags(Token $token): array
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
