<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

use Medas\Core\Attributes\Service;

#[Service]
readonly class StructureFinder
{
    private const array NON_BLOCK_CURLY_BRACE_PREFIXES = [
        T_OBJECT_OPERATOR,
        T_VARIABLE,
        T_SQUARE_BRACKET_CLOSE,
        T_NULLSAFE_OBJECT_OPERATOR
    ];

    private array $typeDeclarationTypes;
    private array $visibilityKeywords;

    public function __construct(
        private TokenGroups $tokenGroups,
    )
    {
        $this->typeDeclarationTypes = $this->tokenGroups->typeDeclarationTypes();
        $this->visibilityKeywords = $this->tokenGroups->visibilityKeywords();
    }

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
        $indicesToRemove = [];
        $i = 0;

        foreach ($tokens as $token) {
            if ($token->is(T_WHITESPACE)) {
                $indicesToRemove[] = $i;
            }

            ++$i;
        }

        foreach (array_reverse($indicesToRemove) as $index) {
            $tokens->remove($index);
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

        if ($job->inAttribute) {
            if ($token->is(T_SQUARE_BRACKET_OPEN)) {
                ++$job->inAttributeBracketDepth;
            }
            elseif ($token->is(T_SQUARE_BRACKET_CLOSE)) {
                if ($job->inAttributeBracketDepth === 0) {
                    // This token closes an attribute
                    $job->inAttribute = false;
                }
                else {
                    --$job->inAttributeBracketDepth;
                }
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
            // The next token starts on a new line
            $job->startNewStatementBeforeNext = true;
        }
        elseif ($job->forClauseDepth === 0 && $token->is(T_SEMICOLON)) {
            // The next token starts on a new line
            $job->startNewStatementBeforeNext = true;
            $job->inUseStatement = false;
        }
        elseif ($token->is(T_COLON)) {
            // Colons in switch statements
            if (array_key_exists($job->blockDepth, $job->switchBlockDepths)
                    && $job->switchBlockDepths[$job->blockDepth] === $job->parenthesesDepth) {
                $job->startNewStatementBeforeNext = true;
            }
        }
        elseif ($token->is(T_CURLY_BRACKET_OPEN) && $this->curlyBraceOpenRelatedToBlocks($job, $token)) {
            // Store the current open block
            $job->openBlocks[] = $job->block;

            // The next token starts in a new block
            $newBlock = new Block(++$job->blockDepth, $job->statement);

            $job->block->appendBlock($newBlock);

            $job->block = $newBlock;
            $job->statement = $job->block->appendNewStatement();

            if ($job->matchState->matchClauseDepth || $job->matchState->nextBraceOpensMatchClause) {
                $job->matchState->nextBraceOpensMatchClause = false;

                ++$job->matchState->matchClauseDepth;
            }
        }
        elseif ($token->is(T_ATTRIBUTE)) {
            // The next token is in an attribute
            $job->inAttribute = true;
        }
        elseif ($token->is(T_DOUBLE_QUOTE)) {
            if ($job->ignoreNextDoubleQuote) {
                // This double quote _closed_ a string already
                $job->ignoreNextDoubleQuote = false;
            }
            else {
                // The next token is in a string
                $job->inString = true;
            }
        }
        elseif ($token->is(T_FOR)) {
            $job->nextBraceOpensForClause = true;
        }
        elseif ($token->is(T_START_HEREDOC)) {
            $job->inString = true;
        }
        elseif ($token->is(T_END_HEREDOC)) {
            $job->inString = false;
        }
        elseif ($token->is(T_ROUND_BRACKET_OPEN)) {
            if (($job->forClauseDepth || $job->nextBraceOpensForClause)) {
                $job->nextBraceOpensForClause = false;

                ++$job->forClauseDepth;
            }

            ++$job->parenthesesDepth;
        }
        elseif ($job->forClauseDepth && $token->is(T_ROUND_BRACKET_CLOSE)) {
            --$job->forClauseDepth;
        }
        elseif (
            $token->is(T_CURLY_BRACKET_CLOSE)
            && $this->curlyBraceCloseRelatedToBlocks($job, $token)
            && $job->matchState->matchClauseDepth === 0
        ) {
            // The next token starts on a new line
            $job->startNewStatementBeforeNext = true;
        }
        elseif ($job->matchState->matchClauseDepth && $token->is(T_DOUBLE_ARROW)) {
            $job->matchState->nextCommaAtThisDepthEndsStatement = $job->parenthesesDepth;
        }
        elseif ($token->is(T_COMMA)) {
            if ($job->matchState->nextCommaAtThisDepthEndsStatement === $job->parenthesesDepth) {
                // The next token starts on a new line
                $job->startNewStatementBeforeNext = true;
                $job->matchState->nextCommaAtThisDepthEndsStatement = null;
            }
        }
        elseif ($token->is(T_MATCH)) {
            $job->matchState->nextBraceOpensMatchClause = true;
        }
        elseif ($token->is(T_SWITCH)) {
            $job->switchBlockDepths[$job->blockDepth + 1] = $job->parenthesesDepth;
        }
        elseif (
            $token->is(T_CURLY_BRACKET_CLOSE)
            && $this->curlyBraceCloseRelatedToBlocks($job, $token)
            && $job->matchState->matchClauseDepth
        ) {
            --$job->matchState->matchClauseDepth;
        }

        if ($token->is(T_CURLY_BRACKET_CLOSE) && !$this->curlyBraceCloseRelatedToBlocks($job, $token)) {
            $job->inNonBlockCurlyBrace = false;
        }

        $this->typeDeclarationChecks($job, $token);
    }

    private function curlyBraceOpenRelatedToBlocks(StructureFinder\Job $job, Token $token): bool
    {
        if ($token->previous && $token->previous->is(self::NON_BLOCK_CURLY_BRACE_PREFIXES)) {
            $job->inNonBlockCurlyBrace = true;

            return false;
        }

        return !$job->inUseStatement && !$job->inString;
    }

    private function curlyBraceCloseRelatedToBlocks(StructureFinder\Job $job, Token $token): bool
    {
        if ($token->next && $token->next->is([T_ROUND_BRACKET_OPEN, T_ASSIGNMENT])) {
            return false;
        }

        return !$job->inUseStatement && !$job->inString && !$job->inNonBlockCurlyBrace;
    }

    private function typeDeclarationChecks(StructureFinder\Job $job, Token $token): void
    {
        if ($job->typeDeclarationState->inArguments
                && !$token->inAttribute
                && $token->next
                && $token->is($this->typeDeclarationTypes)) {
            $token->inTypeDeclaration = true;
        }

        if ($job->typeDeclarationState->nextValueIsReturnType) {
            if ($token->is($this->typeDeclarationTypes)) {
                $token->inTypeDeclaration = true;
            }
            else {
                $job->typeDeclarationState->nextValueIsReturnType = false;
            }
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

        if ($job->typeDeclarationState->inArguments && !$token->inAttribute && $token->is(T_ROUND_BRACKET_CLOSE)) {
            $job->typeDeclarationState->inArguments = false;

            if ($token->next && $token->next->is(T_COLON)) {
                $job->typeDeclarationState->nextNextValueIsReturnType = true;
            }
        }

        if ($job->typeDeclarationState->afterVisibilityKeyword) {
            if ($token->is($this->typeDeclarationTypes)) {
                $token->inTypeDeclaration = true;
            }
            elseif ($token->is([T_FUNCTION, T_VARIABLE, T_CONST])) {
                $job->typeDeclarationState->afterVisibilityKeyword = false;
            }
        }

        if ($token->is($this->visibilityKeywords)) {
            $job->typeDeclarationState->afterVisibilityKeyword = true;
        }
    }
}
