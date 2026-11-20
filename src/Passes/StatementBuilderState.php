<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\Passes;

use Medas\PhpTokenizer\{Block, Statement};

/**
 * The state of @class(StatementBuilder) while it walks through the tokens of one file.
 */
class StatementBuilderState
{
    public Block $block;
    public Statement $statement;
    public int $blockDepth = 0;
    public int $parenthesesDepth = 0;
    public bool $startNewStatementBeforeNext = false;
    public bool $nextParenthesisOpensForClause = false;
    public int $forClauseDepth = 0;

    // The ternaries in the current statement still waiting for their colon
    public int $openTernaries = 0;

    // The number of blocks open inside a match, including the match itself
    public int $matchClauseDepth = 0;

    // The parentheses depth of the => of the current match arm; the next comma at that depth ends the arm
    public int|null $nextCommaAtThisDepthEndsStatement = null;

    /** @var Block[] The blocks around the current one, innermost last */
    public array $openBlocks = [];

    /** @var array<int, int> The parentheses depth of each switch, by the depth of its block */
    public array $switchBlockDepths = [];

    public function __construct(
        public readonly Block $root,
    )
    {
        $this->block = $root;
    }
}
