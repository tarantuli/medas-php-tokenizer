<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

use Medas\Core\Attributes\Service;

#[Service]
class StructureFinder
{
    private const TYPE_DECLARATION_TOKEN_TYPES = [T_QUESTION_MARK, T_STRING, T_PIPE];

    public function determine(TokenCollection $tokens): TokenTree
    {
        $job = new StructureFinder\Job();

        $this->removeWhitespace($tokens);

        $tree = new Block($job->blockDepth, null);
        $job->block = $tree;
        $job->statement = $job->block->appendNewStatement();

        foreach ($tokens as $token) {
            $this->process($job, $token);
        }

        return new TokenTree($tree);
    }

    private function removeWhitespace(TokenCollection $tokens): void
    {
        $counter = 0;

        foreach ($tokens as $token) {
            ++$counter;

            if ($token->is(T_WHITESPACE)) {
                $tokens->remove(--$counter);
            }
        }
    }

    private function process(StructureFinder\Job $job, Token $token): void
    {
        if ($token->is(T_CURLY_BRACKET_CLOSE) && $this->curlyBraceCloseRelatedToBlocks($job, $token)) {
            // Delete the last statement if it's empty
            if (null === $job->statement->firstToken()) {
                $job->block->removeStatement($job->statement);
            }

            // The previous block is closed, return to the last open block
            $job->block = array_pop($job->openBlocks);
            $job->statement = $job->block->appendNewStatement();
            $job->startNewStatementBeforeNext = false;

            $job->switchBlockDepths = array_filter(
                $job->switchBlockDepths,
                fn($depth) => $depth !== $job->blockDepth,
                ARRAY_FILTER_USE_KEY
            );

            --$job->blockDepth;
        }

        if ($job->startNewStatementBeforeNext) {
            $job->statement = $job->block->appendNewStatement();
            $job->startNewStatementBeforeNext = false;
        }

        $token->inAttribute = $job->inAttribute;

        if ($job->inAttribute && $token->is(T_SQUARE_BRACKET_OPEN)) {
            ++$job->inAttributeBracketDepth;
        }

        if ($job->inAttribute && $token->is(T_SQUARE_BRACKET_CLOSE)) {
            if ($job->inAttributeBracketDepth === 0) {
                // This token closes an attribute
                $job->inAttribute = false;
            }
            else {
                --$job->inAttributeBracketDepth;
            }
        }

        if ($job->inString && $token->is(T_DOUBLE_QUOTE)) {
            // This token closes a string
            $job->inString = false;
            $job->ignoreNextDoubleQuote = true;
        }

        if ($token->is(T_ROUND_BRACKET_CLOSE)) {
            --$job->parenthesesDepth;
        }

        $token->block = $job->block;
        $token->statement = $job->statement;
        $token->inString = $job->inString;

        $job->statement->appendToken($token);

        if ($job->statement->tokenCount() === 1) {
            $job->inUseStatement = $job->statement->firstToken()->text === 'use';
        }

        if ($token->is([T_OPEN_TAG])) {
            // Next token starts on a new line
            $job->startNewStatementBeforeNext = true;
        }

        if ($token->is(T_SEMICOLON) && !$job->forClauseDepth) {
            // Next token starts on a new line
            $job->startNewStatementBeforeNext = true;
            $job->inUseStatement = false;
        }

        if ($token->is(T_CURLY_BRACKET_CLOSE)
                && $this->curlyBraceCloseRelatedToBlocks($job, $token)
                && !$job->matchClauseDepth) {
            // Next token starts on a new line
            $job->startNewStatementBeforeNext = true;
        }

        if ($token->is(T_DOUBLE_ARROW) && $job->matchClauseDepth) {
            $job->nextCommaEndsStatement = true;
        }

        if ($token->is(T_COMMA)) {
            if ($job->nextCommaEndsStatement) {
                // Next token starts on a new line
                $job->startNewStatementBeforeNext = true;
                $job->nextCommaEndsStatement = false;
            }
        }

        if ($token->is(T_COLON)) {
            // Colons in switch statements
            if (array_key_exists($job->blockDepth, $job->switchBlockDepths)
                    && $job->switchBlockDepths[$job->blockDepth] === $job->parenthesesDepth) {
                $job->startNewStatementBeforeNext = true;
            }
        }

        if ($token->is(T_CURLY_BRACKET_OPEN) && $this->curlyBraceOpenRelatedToBlocks($job, $token)) {
            // Store the current open block
            $job->openBlocks[] = $job->block;

            // Next token starts in a new block
            $newBlock = new Block(++$job->blockDepth, $job->statement);

            $job->block->appendBlock($newBlock);

            $job->block = $newBlock;
            $job->statement = $job->block->appendNewStatement();
        }

        if ($token->is(T_ATTRIBUTE)) {
            // Next token is in an attribute
            $job->inAttribute = true;
        }

        if ($token->is(T_DOUBLE_QUOTE)) {
            if ($job->ignoreNextDoubleQuote) {
                // This double quote _closed_ a string already
                $job->ignoreNextDoubleQuote = false;
            }
            else {
                // Next token is in a string
                $job->inString = true;
            }
        }

        if ($token->is(T_FOR)) {
            $job->nextBraceOpensForClause = true;
        }

        if ($token->is(T_START_HEREDOC)) {
            $job->inString = true;
        }

        if ($token->is(T_END_HEREDOC)) {
            $job->inString = false;
        }

        if ($token->is(T_ROUND_BRACKET_OPEN) && ($job->forClauseDepth || $job->nextBraceOpensForClause)) {
            $job->nextBraceOpensForClause = false;

            ++$job->forClauseDepth;
        }

        if ($token->is(T_ROUND_BRACKET_OPEN)) {
            ++$job->parenthesesDepth;
        }

        if ($token->is(T_ROUND_BRACKET_CLOSE) && $job->forClauseDepth) {
            --$job->forClauseDepth;
        }

        if ($token->is(T_MATCH)) {
            $job->nextBraceOpensMatchClause = true;
        }

        if ($token->is(T_SWITCH)) {
            $job->switchBlockDepths[$job->blockDepth + 1] = $job->parenthesesDepth;
        }

        if ($token->is(T_CURLY_BRACKET_OPEN)
                && $this->curlyBraceOpenRelatedToBlocks($job, $token)
                && ($job->matchClauseDepth || $job->nextBraceOpensMatchClause)) {
            $job->nextBraceOpensMatchClause = false;

            ++$job->matchClauseDepth;
        }

        if ($token->is(T_CURLY_BRACKET_CLOSE)
                && $this->curlyBraceCloseRelatedToBlocks($job, $token)
                && $job->matchClauseDepth) {
            --$job->matchClauseDepth;
        }

        $this->typeDeclarationChecks($job, $token);
    }

    private function curlyBraceOpenRelatedToBlocks(StructureFinder\Job $job, Token $token): bool
    {
        if ($token->previous && $token->previous->is([T_OBJECT_OPERATOR, T_VARIABLE, T_SQUARE_BRACKET_CLOSE])) {
            return false;
        }

        return !$job->inUseStatement && !$job->inString;
    }

    private function curlyBraceCloseRelatedToBlocks(StructureFinder\Job $job, Token $token): bool
    {
        if ($token->next && $token->next->is([T_ROUND_BRACKET_OPEN, T_ASSIGNMENT])) {
            return false;
        }

        return !$job->inUseStatement && !$job->inString;
    }

    private function typeDeclarationChecks(StructureFinder\Job $job, Token $token): void
    {
        if ($job->typeDeclarationState->inArguments && $token->next && $token->is(self::TYPE_DECLARATION_TOKEN_TYPES)) {
            $token->typeDeclaration = true;
        }

        if ($job->typeDeclarationState->nextValueIsReturnType) {
            if ($token->is(self::TYPE_DECLARATION_TOKEN_TYPES)) {
                $token->typeDeclaration = true;
            }

            $job->typeDeclarationState->nextValueIsReturnType = false;
        }

        if ($job->typeDeclarationState->nextNextValueIsReturnType) {
            $job->typeDeclarationState->nextValueIsReturnType = true;
            $job->typeDeclarationState->nextNextValueIsReturnType = false;
        }

        if ($token->is([T_FN, T_FUNCTION])) {
            $job->typeDeclarationState->nextBracesIsArguments = true;
        }

        if ($job->typeDeclarationState->nextBracesIsArguments && $token->is(T_ROUND_BRACKET_OPEN)) {
            $job->typeDeclarationState->inArguments = true;
            $job->typeDeclarationState->nextBracesIsArguments = false;
        }

        if ($job->typeDeclarationState->inArguments && $token->is(T_ROUND_BRACKET_CLOSE)) {
            if ($token->next && $token->next->is(T_COLON)) {
                $job->typeDeclarationState->nextNextValueIsReturnType = true;
            }
        }
    }
}
