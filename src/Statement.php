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

    /**
     * Puts a token at the start of this statement, and links it to the tokens around it.
     */
    public function prependToken(Token $token): void
    {
        $first = $this->tokens[0] ?? null;

        array_unshift($this->tokens, $token);

        $token->block = $this->block;
        $token->statement = $this;

        if ($first !== null && $first->previous !== $token) {
            $token->previous = $first->previous;
            $token->next = $first;

            if ($first->previous !== null) {
                $first->previous->next = $token;
            }

            $first->previous = $token;
        }

        $this->type = null;
    }

    /**
     * Puts a token at the end of this statement, and links it to the tokens around it. A token that already follows the
     * last token, like when the tokenizer builds the tree, keeps its links.
     */
    public function appendToken(Token $token): void
    {
        $last = $this->tokens[count($this->tokens) - 1] ?? null;

        $this->tokens[] = $token;

        if ($last !== null && $last->next !== $token) {
            $token->previous = $last;
            $token->next = $last->next;

            if ($last->next !== null) {
                $last->next->previous = $token;
            }

            $last->next = $token;
        }

        if ($last !== null && isset($last->context)) {
            $token->context = $last->context;
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

    /**
     * Takes a token out of this statement, and links the tokens before and after it to each other.
     *
     * @throws Exceptions\TokenNotFoundInStatementException
     */
    public function removeToken(Token $token): void
    {
        if (false === $i = array_search($token, $this->tokens, true)) {
            throw new Exceptions\TokenNotFoundInStatementException($token, $this);
        }

        array_splice($this->tokens, $i, 1);

        if ($token->previous?->next === $token) {
            $token->previous->next = $token->next;
        }

        if ($token->next?->previous === $token) {
            $token->next->previous = $token->previous;
        }

        $token->previous = $token->next = null;
        $this->type = null;
    }

    /**
     * @throws Exceptions\TokenNotFoundInStatementException
     */
    public function insertTokenAfter(Token $token, Token $after): void
    {
        if (false === $i = array_search($after, $this->tokens, true)) {
            throw new Exceptions\TokenNotFoundInStatementException($after, $this);
        }

        $token->block = $after->block;
        $token->statement = $after->statement;
        $token->inString = $after->inString;
        $token->inAttribute = $after->inAttribute;

        $token->previous = $after;
        $token->next = $after->next;

        if ($after->next !== null) {
            $after->next->previous = $token;
        }

        $after->next = $token;

        array_splice($this->tokens, $i + 1, 0, [$token]);

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
        return array_find($this->tokens, fn($token) => !$token->is([T_DOC_COMMENT, T_COMMENT]));
    }

    public function containsType(array|int|string $type): bool
    {
        return array_any($this->tokens, fn($token) => $token->is($type));
    }

    public function findToken(array|int|string $type): Token|null
    {
        return array_find($this->tokens, fn($token) => $token->is($type));
    }

    public function getTokenAfter(Token $token): Token|null
    {
        if (false === $i = array_search($token, $this->tokens, true)) {
            throw new Exceptions\TokenNotFoundInStatementException($token, $this);
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

    /**
     * @throws Exceptions\TokenNotFoundInStatementException
     */
    public function getIndex(Token $token): int
    {
        $index = array_search($token, $this->tokens, true);

        if ($index === false) {
            throw new Exceptions\TokenNotFoundInStatementException($token, $this);
        }

        return $index;
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
