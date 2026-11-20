<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

class TokenTree implements \IteratorAggregate
{
    public bool $doResetLinks = false;

    public function __construct(
        private readonly Block $block,
    )
    {
    }

    /**
     * foreach ($tree) returns the tokens in this tree. foreach ($tree->block()) returns the statements.
     *
     * @return Token[]|\Generator
     * @noinspection PhpDocSignatureInspection
     */
    public function getIterator(): \Generator
    {
        if ($this->doResetLinks) {
            $this->resetLinks();
        }

        foreach ($this->block as $statement) {
            yield from $statement;
        }
    }

    private function resetLinks(): void
    {
        $previousToken = null;
        $token = null;

        foreach ($this->block as $statement) {
            foreach ($statement as $token) {
                if ($previousToken) {
                    $previousToken->next = $token;
                    $token->previous = $previousToken;
                }

                $previousToken = $token;
            }
        }

        if (null !== $token) {
            $token->next = null;
        }

        $this->doResetLinks = false;
    }

    /**
     * Checks that every token links to the tokens before and after it in the tree, and belongs to the statement and
     * block it is in. Meant for tests, to find the formatter that leaves the tree broken.
     *
     * @throws Exceptions\TreeIsInconsistent
     */
    public function assertConsistent(): void
    {
        $previous = null;

        foreach ($this->block as $statement) {
            foreach ($statement as $token) {
                if ($token->statement !== $statement) {
                    $this->fail('the token belongs to another statement', $token);
                }

                if ($token->block !== $statement->block) {
                    $this->fail('the token belongs to another block', $token);
                }

                if ($token->previous !== $previous) {
                    $this->fail('previous is not the token before it', $token);
                }

                if ($previous !== null && $previous->next !== $token) {
                    $this->fail('next of the token before it is not this token', $token);
                }

                $previous = $token;
            }
        }

        if ($previous?->next !== null) {
            $this->fail('the last token has a next token', $previous);
        }
    }

    private function fail(string $problem, Token $token): never
    {
        throw new Exceptions\TreeIsInconsistent($problem, $token->text, $token->line);
    }

    public function doResetLinks(): void
    {
        $this->doResetLinks = true;
    }

    public function statements(): Block
    {
        // This method helps with readability in: foreach ($tree->statements() as $statement)
        return $this->block;
    }

    public function block(): Block
    {
        return $this->block;
    }
}
