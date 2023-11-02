<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

use Medas\Core\Attributes\Service;

#[Service]
class StructureFinder
{
    private Block $block;
    private Statement $statement;
    private int $blockDepth;
    private int $parenthesesDepth;

    private bool $inString;
    private bool $inAttribute;
    private int $inAttributeBracketDepth;

    private bool $inUseStatement;

    private array $openBlocks = [];
    private bool $ignoreNextDoubleQuote;
    private bool $startNewStatementBeforeNext;
    private bool $nextBraceOpensForClause;

    private int $forClauseDepth;
    /** The key is the block depth, the value is the parentheses depth  */
    private array $switchBlockDepths;
    private int $matchClauseDepth;
    private bool $nextCommaEndsStatement;
    private bool $nextBraceOpensMatchClause;

    public function determine(TokenCollection $tokens): TokenTree
    {
        $this->reset();
        $this->removeWhitespace($tokens);

        $tree = new Block($this->blockDepth, null);

        $this->block = $tree;
        $this->statement = $this->block->appendNewStatement();

        foreach ($tokens as $token) {
            $this->process($token);
        }

        return new TokenTree($tree);
    }

    private function reset(): void
    {
        $this->blockDepth = 0;
        $this->parenthesesDepth = 0;
        $this->inUseStatement = false;
        $this->inString = false;

        $this->inAttribute = false;
        $this->inAttributeBracketDepth = 0;

        $this->openBlocks = [];

        $this->ignoreNextDoubleQuote = false;
        $this->startNewStatementBeforeNext = false;
        $this->nextBraceOpensForClause = false;
        $this->forClauseDepth = 0;
        $this->switchBlockDepths = [];

        $this->matchClauseDepth = 0;
        $this->nextBraceOpensMatchClause = false;
        $this->nextCommaEndsStatement = false;
    }

    private function removeWhitespace(TokenCollection $tokens): void
    {
        {
            $counter = 0;
            foreach ($tokens as $token) {
                ++$counter;
                if ($token->is(T_WHITESPACE)) {
                    $tokens->remove(--$counter);
                }
            }
        }
    }

    private function process(Token $token): void
    {
        if ($token->is(T_CURLY_BRACKET_CLOSE) && !$this->inUseStatement) {
            // Delete the last statement if it's empty
            if (null === $this->statement->firstToken()) {
                $this->block->removeStatement($this->statement);
            }

            // The previous block is closed, return to the last open block
            $this->block = array_pop($this->openBlocks);
            $this->statement = $this->block->appendNewStatement();
            $this->startNewStatementBeforeNext = false;

            $this->switchBlockDepths = array_filter(
                $this->switchBlockDepths,
                fn($depth) => $depth !== $this->blockDepth,
                ARRAY_FILTER_USE_KEY);

            --$this->blockDepth;
        }

        if ($this->startNewStatementBeforeNext) {
            $this->statement = $this->block->appendNewStatement();
            $this->startNewStatementBeforeNext = false;
        }

        $token->inAttribute = $this->inAttribute;

        if ($this->inAttribute && $token->is(T_SQUARE_BRACKET_OPEN)) {
            ++$this->inAttributeBracketDepth;
        }

        if ($this->inAttribute && $token->is(T_SQUARE_BRACKET_CLOSE)) {
            if ($this->inAttributeBracketDepth === 0) {
                // This token closes an attribute
                $this->inAttribute = false;
            }
            else {
                --$this->inAttributeBracketDepth;
            }
        }

        if ($this->inString && $token->is(T_DOUBLE_QUOTE)) {
            // This token closes a string
            $this->inString = false;
            $this->ignoreNextDoubleQuote = true;
        }

        if ($token->is(T_ROUND_BRACKET_CLOSE)) {
            --$this->parenthesesDepth;
        }

        $token->block = $this->block;
        $token->statement = $this->statement;
        $token->inString = $this->inString;

        $this->statement->appendToken($token);

        if ($this->statement->tokenCount() === 1) {
            $this->inUseStatement = $this->statement->firstToken()->text === 'use';
        }

        if ($token->is([T_OPEN_TAG])) {
            // Next token starts on a new line
            $this->startNewStatementBeforeNext = true;
        }

        if ($token->is(T_SEMICOLON) && !$this->forClauseDepth) {
            // Next token starts on a new line
            $this->startNewStatementBeforeNext = true;
        }

        if ($token->is(T_CURLY_BRACKET_CLOSE) && !$this->matchClauseDepth && !$this->inUseStatement) {
            // Next token starts on a new line
            $this->startNewStatementBeforeNext = true;
        }

        if ($token->is(T_DOUBLE_ARROW) && $this->matchClauseDepth) {
            $this->nextCommaEndsStatement = true;
        }

        if ($token->is(T_COMMA)) {
            if ($this->nextCommaEndsStatement) {
                // Next token starts on a new line
                $this->startNewStatementBeforeNext = true;
                $this->nextCommaEndsStatement = false;
            }
        }

        if ($token->is(T_COLON)) {
            // Colons in switch statements
            if (array_key_exists($this->blockDepth, $this->switchBlockDepths)
                && $this->switchBlockDepths[$this->blockDepth] === $this->parenthesesDepth) {
                $this->startNewStatementBeforeNext = true;
            }
        }

        if ($token->is(T_CURLY_BRACKET_OPEN) && !$this->inUseStatement) {
            // Store the current open block
            $this->openBlocks[] = $this->block;

            // Next token starts in a new block
            $newBlock = new Block(++$this->blockDepth, $this->statement);
            $this->block->appendBlock($newBlock);
            $this->block = $newBlock;
            $this->statement = $this->block->appendNewStatement();
        }

        if ($token->is(T_ATTRIBUTE)) {
            // Next token is in an attribute
            $this->inAttribute = true;
        }

        if ($token->is(T_DOUBLE_QUOTE)) {
            if ($this->ignoreNextDoubleQuote) {
                // This double quote _closed_ a string already
                $this->ignoreNextDoubleQuote = false;
            }
            else {
                // Next token is in a string
                $this->inString = true;
            }
        }

        if ($token->is(T_FOR)) {
            $this->nextBraceOpensForClause = true;
        }

        if ($token->is(T_ROUND_BRACKET_OPEN) && ($this->forClauseDepth || $this->nextBraceOpensForClause)) {
            $this->nextBraceOpensForClause = false;
            ++$this->forClauseDepth;
        }

        if ($token->is(T_ROUND_BRACKET_OPEN)) {
            ++$this->parenthesesDepth;
        }

        if ($token->is(T_ROUND_BRACKET_CLOSE) && $this->forClauseDepth) {
            --$this->forClauseDepth;
        }

        if ($token->is(T_MATCH)) {
            $this->nextBraceOpensMatchClause = true;
        }

        if ($token->is(T_SWITCH)) {
            $this->switchBlockDepths[$this->blockDepth + 1] = $this->parenthesesDepth;
        }

        if ($token->is(T_CURLY_BRACKET_OPEN) && ($this->matchClauseDepth || $this->nextBraceOpensMatchClause)) {
            $this->nextBraceOpensMatchClause = false;
            ++$this->matchClauseDepth;
        }

        if ($token->is(T_CURLY_BRACKET_CLOSE) && $this->matchClauseDepth) {
            --$this->matchClauseDepth;
        }
    }
}
