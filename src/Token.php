<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

class Token extends \PhpToken
{
    public Block $block;
    public Statement $statement;
    public bool $inString = false;
    public bool $inAttribute = false;
    public bool $inTypeDeclaration = false;
    public Contexts\Context $context;
    public bool $spaceAfter = false;
    public bool $lineBreakAfter = false;
    public int $extraSpacesAfter = 0;
    public Token|null $previous = null;
    public Token|null $next = null;

    public function __serialize(): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'block' => isset($this->block) ? spl_object_id($this->block) : null,
            'statement' => isset($this->statement) ? spl_object_id($this->statement) : null,
            'inString' => $this->inString,
            'inAttribute' => $this->inAttribute,
            'inTypeDeclaration' => $this->inTypeDeclaration,
            'context' => $this->context ?? null,
            'spaceAfter' => $this->spaceAfter,
            'lineBreakAfter' => $this->lineBreakAfter,
            'extraSpacesAfter' => $this->extraSpacesAfter,
            'previous' => $this->previous ? spl_object_id($this->previous) : null,
            'next' => $this->next ? spl_object_id($this->next) : null,
        ];
    }

    public function isTrueFalseNull(): bool
    {
        return $this->is(T_STRING) && in_array(strtolower($this->text), ['true', 'false', 'null'], true);
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
