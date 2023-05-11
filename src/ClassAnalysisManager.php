<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

use Medas\Core\Attributes\Service;

#[Service]
class ClassAnalysisManager
{
    public function __construct(
        private readonly ClassAnalyser\ClassAnalyser $analyser,
    )
    {
    }

    public function forTokens(TokenTree $tree): ClassAnalyser\ClassAnalysis
    {
        return $this->analyser->analyseTokenTree($tree);
    }
}
