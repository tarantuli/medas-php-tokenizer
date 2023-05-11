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
