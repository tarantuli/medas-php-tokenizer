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
    public int $inAttributeBracketDepth = 0;
    public bool $inUseStatement = false;
    public array $openBlocks = [];
    public bool $ignoreNextDoubleQuote = false;
    public bool $startNewStatementBeforeNext = false;
    public bool $nextBraceOpensForClause = false;
    public int $forClauseDepth = 0;

    /** The key is the block depth, the value is the parentheses depth  */
    public array $switchBlockDepths = [];

    public int $matchClauseDepth = 0;
    public bool $nextCommaEndsStatement = false;
    public bool $nextBraceOpensMatchClause = false;
    public TypeDeclarationState $typeDeclarationState;

    public function __construct()
    {
        $this->typeDeclarationState = new TypeDeclarationState();
    }
}
