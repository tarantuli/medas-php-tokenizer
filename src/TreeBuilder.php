<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

use Medas\Core\Attributes\Service;

#[Service]
readonly class TreeBuilder
{
    public function __construct(
        private ContextAdder    $contextAdder,
        private StructureFinder $structureFinder,
        private Tokenizer       $tokenizer,
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
