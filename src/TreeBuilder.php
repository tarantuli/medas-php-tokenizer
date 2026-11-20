<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

use Medas\Core\Attributes\Service;

/**
 * Builds a token tree from code by running every @class(Passes\TokenizerPass), in the order of
 * @class(Passes\TokenizerPasses).
 */
#[Service]
readonly class TreeBuilder
{
    public function __construct(
        private Passes\TokenizerPasses $passes,
        private Tokenizer              $tokenizer,
    )
    {
    }

    public function fromCode(string $code): TokenTree
    {
        return $this->fromCollection($this->tokenizer->tokenize($code));
    }

    public function fromCollection(TokenCollection $collection): TokenTree
    {
        $job = new Passes\TokenizerJob($collection);

        foreach ($this->passes->ordered() as $pass) {
            $pass->run($job);
        }

        return $job->tree;
    }
}
