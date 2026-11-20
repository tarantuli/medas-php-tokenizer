<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

class Token extends \PhpToken
{
    public Block $block;
    public Statement $statement;
    public bool $inString = false;
    public bool $inAttribute = false;
    public bool $closesAttribute = false;
    public bool $inTypeDeclaration = false;
    public Contexts\Context|null $context = null;
    public bool $spaceAfter = false;
    public bool $lineBreakAfter = false;
    public int $extraSpacesAfter = 0;
    public Token|null $previous = null;
    public Token|null $next = null;

    // The other half of ( ), [ ], { }, #[ ], ${ } and {$ }; null for every other token
    public Token|null $matchingBracket = null;

    // The number of open brackets around this token, not counting the braces of blocks. A bracket has the depth of
    // the code around it, not of the code inside it.
    public int $bracketDepth = 0;

    // Set on a { or an alternative syntax : that opens a block
    public BlockType|null $opensBlock = null;

    // Set on a } that closes a block, and on endif, endforeach, else, elseif etc. that end an alternative syntax block
    public bool $closesBlock = false;

    // The whitespace after this token in the source: the number of line breaks, and when there are none, the number of
    // spaces and tabs. Set by Passes\Lexer; formatters change spaceAfter and lineBreakAfter, never these.
    public int $originalLineBreaksAfter = 0;
    public int $originalSpacesAfter = 0;

    /**
     * Creates a token for code that a formatter adds. The token id is looked up from the text, the line and position
     * are taken from a token nearby, so error messages point to the right place.
     */
    public static function fromText(string $text, Token $nearby): static
    {
        $tokens = static::tokenize('<?php ' . $text);
        $id = isset($tokens[1]) && $tokens[1]->text === $text ? $tokens[1]->id : T_STRING;

        return new static($id, $text, $nearby->line, $nearby->pos);
    }

    public function __serialize(): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'line' => $this->line,
            'column' => $this->pos,
            'block' => isset($this->block) ? spl_object_id($this->block) : null,
            'statement' => isset($this->statement) ? spl_object_id($this->statement) : null,
            'inString' => $this->inString,
            'inAttribute' => $this->inAttribute,
            'closesAttribute' => $this->closesAttribute,
            'inTypeDeclaration' => $this->inTypeDeclaration,
            'context' => $this->context ?? null,
            'spaceAfter' => $this->spaceAfter,
            'lineBreakAfter' => $this->lineBreakAfter,
            'extraSpacesAfter' => $this->extraSpacesAfter,
            'previous' => $this->previous ? spl_object_id($this->previous) : null,
            'next' => $this->next ? spl_object_id($this->next) : null,
            'matchingBracket' => $this->matchingBracket ? spl_object_id($this->matchingBracket) : null,
            'bracketDepth' => $this->bracketDepth,
            'opensBlock' => $this->opensBlock?->name,
            'closesBlock' => $this->closesBlock,
            'originalLineBreaksAfter' => $this->originalLineBreaksAfter,
            'originalSpacesAfter' => $this->originalSpacesAfter,
        ];
    }

    public function isTrueFalseNull(): bool
    {
        return $this->is(T_STRING)
            && in_array(strtolower($this->text), ['true', 'false', 'null'], true);
    }

    public function isLastToken(): bool
    {
        return $this === $this->statement->lastToken();
    }

    public function isFirstToken(): bool
    {
        return $this === $this->statement->firstToken();
    }

    public function context(Contexts\Context $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function id(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function text(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function lineBreakAfter(bool $lineBreakAfter = true): void
    {
        $this->lineBreakAfter = $lineBreakAfter;
    }

    public function spaceAfter(bool $spaceAfter = true): void
    {
        $this->spaceAfter = $spaceAfter;
    }
}
