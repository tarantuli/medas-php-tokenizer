<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\Passes;

use Medas\Core\Attributes\Service;
use Medas\PhpTokenizer\{Block, BlockType, Token, TokenTree};

/**
 * Puts the tokens into statements and the statements into blocks, using the blocks found by @class(BlockTypeResolver).
 *
 * A statement ends after ;, after the opening and closing brace of a block, after the : of a switch case, and after
 * the , that ends a match arm. A ; inside the parentheses of for (...) doesn't end a statement.
 */
#[Service]
readonly class StatementBuilder implements TokenizerPass
{
    public function runsAfter(): array
    {
        return [BlockTypeResolver::class];
    }

    public function run(TokenizerJob $job): void
    {
        $state = new StatementBuilderState(new Block(0, null, BlockType::File));
        $state->statement = $state->block->appendNewStatement();
        $state->block->opener = $state->statement;

        foreach ($job->tokens as $token) {
            $this->process($state, $token);
        }

        $job->root = $state->root;
        $job->tree = new TokenTree($state->root);
    }

    private function process(StatementBuilderState $state, Token $token): void
    {
        if ($token->closesBlock) {
            $this->closeBlock($state);
        }

        if ($state->startNewStatementBeforeNext) {
            $state->statement = $state->block->appendNewStatement();
            $state->startNewStatementBeforeNext = false;
            $state->openTernaries = 0;
        }

        if ($token->is(T_ROUND_BRACKET_CLOSE)) {
            --$state->parenthesesDepth;
        }

        $token->block = $state->block;
        $token->statement = $state->statement;

        $state->statement->appendToken($token);

        if ($token->is(T_OPEN_TAG)) {
            $state->startNewStatementBeforeNext = true;
        }
        elseif ($state->forClauseDepth === 0 && $token->is(T_SEMICOLON)) {
            $state->startNewStatementBeforeNext = true;
        }
        elseif ($token->opensBlock !== null) {
            $this->openBlock($state, $token->opensBlock);
        }
        elseif ($token->is(T_QUESTION_MARK) && !$token->next?->is(T_COLON)) {
            ++$state->openTernaries;
        }
        elseif ($token->is(T_COLON)) {
            if ($state->openTernaries > 0 && !$token->previous?->is(T_QUESTION_MARK)) {
                // The colon of a ternary, like in "case $a ? 1 : 2:"
                --$state->openTernaries;
            }
            elseif (array_key_exists($state->blockDepth, $state->switchBlockDepths)
                    && $state->switchBlockDepths[$state->blockDepth] === $state->parenthesesDepth) {
                // The colon of a switch case
                $state->startNewStatementBeforeNext = true;
            }
        }
        elseif ($token->is(T_FOR)) {
            $state->nextParenthesisOpensForClause = true;
        }
        elseif ($token->is(T_ROUND_BRACKET_OPEN)) {
            if ($state->forClauseDepth || $state->nextParenthesisOpensForClause) {
                $state->nextParenthesisOpensForClause = false;

                ++$state->forClauseDepth;
            }

            ++$state->parenthesesDepth;
        }
        elseif ($state->forClauseDepth && $token->is(T_ROUND_BRACKET_CLOSE)) {
            --$state->forClauseDepth;
        }
        elseif ($token->closesBlock && $token->is(T_CURLY_BRACKET_CLOSE)) {
            if ($state->matchClauseDepth === 0) {
                $state->startNewStatementBeforeNext = true;
            }
            else {
                --$state->matchClauseDepth;
            }
        }
        elseif ($state->matchClauseDepth
                && $token->is(T_DOUBLE_ARROW)
                && $state->nextCommaAtThisDepthEndsStatement === null) {
            // The => of a match arm. A later one, like in "1 => ['a' => 1, 'b' => 2]", is part of the arm's value.
            $state->nextCommaAtThisDepthEndsStatement = $token->bracketDepth;
        }
        elseif ($token->is(T_COMMA)) {
            // Counting all brackets, so a comma inside "1 => [2, 3]" doesn't end the arm
            if ($state->nextCommaAtThisDepthEndsStatement === $token->bracketDepth) {
                // The comma ends a match arm
                $state->startNewStatementBeforeNext = true;
                $state->nextCommaAtThisDepthEndsStatement = null;
            }
        }
        elseif ($token->is(T_SWITCH)) {
            $state->switchBlockDepths[$state->blockDepth + 1] = $state->parenthesesDepth;
        }
    }

    private function openBlock(StatementBuilderState $state, BlockType $type): void
    {
        $state->openBlocks[] = $state->block;

        $block = new Block(++$state->blockDepth, $state->statement, $type);

        $state->block->appendBlock($block);
        $state->block = $block;
        $state->statement = $block->appendNewStatement();

        // Inside a match, every nested block is counted, so the } that closes it doesn't end the match arm
        if ($state->matchClauseDepth || $type === BlockType::Match) {
            ++$state->matchClauseDepth;
        }

        // A match in a match arm keeps the comma that will end the outer arm for after its }
        if ($type === BlockType::Match) {
            $state->outerMatchArmCommaDepths[] = $state->nextCommaAtThisDepthEndsStatement;
            $state->nextCommaAtThisDepthEndsStatement = null;
        }
    }

    private function closeBlock(StatementBuilderState $state): void
    {
        if ($state->statement->firstToken() === null) {
            $state->block->removeStatement($state->statement);
        }

        if ($state->block->type === BlockType::Match) {
            $state->nextCommaAtThisDepthEndsStatement = array_pop($state->outerMatchArmCommaDepths);
        }

        $state->block = array_pop($state->openBlocks);
        $state->statement = $state->block->appendNewStatement();
        $state->startNewStatementBeforeNext = false;
        $state->openTernaries = 0;

        unset($state->switchBlockDepths[$state->blockDepth]);

        --$state->blockDepth;
    }
}
