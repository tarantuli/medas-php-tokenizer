<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\ClassAnalyser;

use Medas\Core\Attributes\Service;
use Medas\PhpTokenizer\{TokenTree, TreeBuilder};

#[Service]
class ClassAnalyser
{
    public function __construct(
        private readonly ImportsFinder   $importsFinder,
        private readonly NameFinder      $nameFinder,
        private readonly ReferenceFinder $referenceFinder,
        private readonly TreeBuilder     $treeBuilder,
    )
    {
    }

    public function analyse(string $code): ClassAnalysis
    {
        $tree = $this->treeBuilder->fromCode($code);

        return $this->analyseTokenTree($tree);
    }

    public function analyseTokenTree(TokenTree $tree): ClassAnalysis
    {
        $results = new ClassAnalysis();

        $this->importsFinder->find($tree, $results);
        $this->nameFinder->find($tree, $results);
        $this->referenceFinder->find($tree, $results);

        return $results;
    }
}
