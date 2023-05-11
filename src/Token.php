<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

class Token extends \PhpToken
{
    public Block $block;
    public Statement $statement;
    public bool $inString;
    public bool $inAttribute;
    public Contexts\Context $context;

    public bool $spaceAfter = false;
    public bool $lineBreakAfter = false;

    public Token|null $previous = null;
    public Token|null $next = null;

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
}
