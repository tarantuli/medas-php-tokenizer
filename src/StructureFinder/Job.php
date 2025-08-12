<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\StructureFinder;

use Medas\PhpTokenizer\{Block, Statement};

class Job
{
    public Block $block;
    public Statement $statement;
    public int $blockDepth = 0;
    public int $parenthesesDepth = 0;
    public bool $inString = false;
    public bool $inAttribute = false;
    public bool $inNonBlockCurlyBrace = false;
    public int $inAttributeBracketDepth = 0;
    public bool $inUseStatement = false;
    public array $openBlocks = [];
    public bool $ignoreNextDoubleQuote = false;
    public bool $startNewStatementBeforeNext = false;
    public bool $nextBraceOpensForClause = false;
    public int $forClauseDepth = 0;

    /** The key is the block depth, the value is the parentheses depth  */
    public array $switchBlockDepths = [];

    public TypeDeclarationState $typeDeclarationState;
    public MatchState $matchState;

    public function __construct()
    {
        $this->typeDeclarationState = new TypeDeclarationState();
        $this->matchState = new MatchState();
    }
}
