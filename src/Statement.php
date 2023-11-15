<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

class Statement implements \IteratorAggregate
{
    public bool $blankLineAfter = false;
    public int $additionalDepth = 0;

    // This property is not null when a single statement has been split up for formatting purposes
    public Statement|null $rootStatement = null;

    private StatementTypes\StatementType|null $type = null;

    /** @var Token[] */
    private array $tokens = [];

    public function __construct(
        public Block $block,
    )
    {
    }

    public function tokenCount(): int
    {
        return count($this->tokens);
    }

    /**
     * @return Token[]|\Generator
     * @noinspection PhpDocSignatureInspection
     */
    public function getIterator(): \Generator
    {
        yield from $this->tokens;
    }

    public function blankLineAfter(bool $blankLineAfter = true): void
    {
        $this->blankLineAfter = $blankLineAfter;
    }

    public function type(callable $setter): StatementTypes\StatementType
    {
        if ($this->type === null) {
            $this->type = $setter();
        }

        return $this->type;
    }

    public function setType(StatementTypes\StatementType $type): void
    {
        $this->type = $type;
    }

    public function prependToken(Token $token): void
    {
        array_unshift($this->tokens, $token);

        $token->block = $this->block;
        $token->statement = $this;

        if (isset($this->tokens[1])) {
            $token->next = $this->tokens[1];
            $this->tokens[1]->previous = $token;
        }

        $this->type = null;
    }

    public function appendToken(Token $token): void
    {
        $this->tokens[] = $token;
        $newIndex = count($this->tokens) - 1;

        if ($newIndex > 0) {
            $this->tokens[$newIndex - 1]->next = $token;
            $token->previous = $this->tokens[$newIndex - 1];

            if (isset($token->previous->context)) {
                $token->context = $token->previous->context;
            }
        }

        $token->block = $this->block;
        $token->statement = $this;
        $this->type = null;
    }

    public function moveTokenAfter(Token $token, Token $after): void
    {
        $this->removeToken($token);
        $this->insertTokenAfter($token, $after);

        $this->type = null;
    }

    public function removeToken(Token $token): void
    {
        if (false === $i = array_search($token, $this->tokens, true)) {
            throw new Exceptions\TokenNotFoundinStatementException($token, $this);
        }

        unset($this->tokens[$i]);

        $this->tokens = array_values($this->tokens);

        if (isset($this->tokens[$i - 1]) && isset($this->tokens[$i])) {
            $this->tokens[$i - 1]->next = $this->tokens[$i];
            $this->tokens[$i]->previous = $this->tokens[$i - 1];
        }
        elseif (isset($this->tokens[$i - 1])) {
            $this->tokens[$i - 1]->next = null;
        }
        elseif (isset($this->tokens[$i])) {
            $this->tokens[$i]->previous = null;
        }

        $token->previous = $token->next = null;
        $this->type = null;
    }

    public function insertTokenAfter(Token $token, Token $after): void
    {
        if (false === $i = array_search($after, $this->tokens, true)) {
            throw new Exceptions\TokenNotFoundinStatementException($token, $this);
        }

        $token->block = $after->block;
        $token->statement = $after->statement;
        $token->inString = $after->inString;
        $token->inAttribute = $after->inAttribute;
        $token->previous = $after;
        $after->next = $token;

        if (isset($this->tokens[$i + 1])) {
            $token->next = $this->tokens[$i + 1];
            $this->tokens[$i + 1]->previous = $token;
        }

        array_splice($this->tokens, $i + 1, 0, [$token]);

        $this->tokens = array_values($this->tokens);
        $this->type = null;
    }

    public function lastToken(): Token|null
    {
        return $this->tokens[count($this->tokens) - 1] ?? null;
    }

    public function firstToken(): Token|null
    {
        return $this->tokens[0] ?? null;
    }

    public function firstNonCommentToken(): Token|null
    {
        foreach ($this->tokens as $token) {
            if (!$token->is([T_DOC_COMMENT, T_COMMENT])) {
                return $token;
            }
        }

        return null;
    }

    public function containsType(array|int|string $type): bool
    {
        foreach ($this->tokens as $token) {
            if ($token->is($type)) {
                return true;
            }
        }

        return false;
    }

    public function findToken(array|int|string $type): ?Token
    {
        foreach ($this->tokens as $token) {
            if ($token->is($type)) {
                return $token;
            }
        }

        return null;
    }

    public function getTokenAfter(Token $token): Token|null
    {
        if (false === $i = array_search($token, $this->tokens, true)) {
            throw new Exceptions\TokenNotFoundinStatementException($token, $this);
        }

        return $this->tokens[$i + 1] ?? null;
    }

    public function getToken(int $index): Token|null
    {
        if ($index < 0) {
            return $this->tokens[count($this->tokens) + $index] ?? null;
        }

        return $this->tokens[$index] ?? null;
    }

    public function getIndex(Token $token): int|null
    {
        $index = array_search($token, $this->tokens);

        return $index === false ? null : $index;
    }

    public function mergeWithPrevious(): void
    {
        $this->block->mergeWithPrevious($this);
    }

    public function previous(): Statement|null
    {
        return $this->block->getPreviousStatement($this);
    }

    public function next(): Statement|null
    {
        return $this->block->getNextStatement($this);
    }
}
