<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\StructureFinder;

class MatchState
{
    public int $matchClauseDepth = 0;
    public int|null $nextCommaAtThisDepthEndsStatement = null;
    public bool $nextBraceOpensMatchClause = false;
}
