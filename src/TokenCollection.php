<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

class TokenCollection implements \IteratorAggregate, \Countable
{
    /** @var Token[] */
    private array $tokens = [];

    private string $sourceHash;
    private Token|null $previousToken = null;

    /**
     * @return Token[]|\Generator
     * @noinspection PhpDocSignatureInspection
     */
    public function getIterator(): \Generator
    {
        yield from $this->tokens;
    }

    public function add(Token $token): void
    {
        $this->tokens[] = $token;

        if ($this->previousToken) {
            $this->previousToken->next = $token;
            $token->previous = $this->previousToken;
        }

        $this->previousToken = $token;
    }

    public function remove(int $index): void
    {
        array_splice($this->tokens, $index, 1);

        if (array_key_exists($index - 1, $this->tokens)) {
            if (array_key_exists($index, $this->tokens)) {
                $this->tokens[$index - 1]->next = $this->tokens[$index];
                $this->tokens[$index]->previous = $this->tokens[$index - 1];
            }
            else {
                $this->tokens[$index - 1]->next = null;
            }
        }
        else {
            if (array_key_exists($index, $this->tokens)) {
                $this->tokens[$index]->previous = null;
            }
        }
    }

    public function count(): int
    {
        return count($this->tokens);
    }

    public function isEmpty(): bool
    {
        return $this->tokens === [];
    }

    public function sourceHash(): string
    {
        return $this->sourceHash;
    }

    public function setSourceHash(string $sourceHash): self
    {
        $this->sourceHash = $sourceHash;

        return $this;
    }
}
