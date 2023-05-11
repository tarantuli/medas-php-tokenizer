<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

use Medas\Core\Attributes\Service;

#[Service]
class TreeBuilder
{
    public function __construct(
        private readonly ContextAdder    $contextAdder,
        private readonly StructureFinder $structureFinder,
        private readonly Tokenizer       $tokenizer,
    )
    {
    }

    public function fromCode(string $code): TokenTree
    {
        return $this->fromCollection($this->tokenizer->tokenize($code));
    }

    public function fromCollection(TokenCollection $collection): TokenTree
    {
        $tree = $this->structureFinder->determine($collection);
        $this->contextAdder->add($tree);

        return $tree;
    }
}
