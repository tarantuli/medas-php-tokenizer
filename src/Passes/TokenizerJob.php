<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\Passes;

use Medas\PhpTokenizer\{Block, Token, TokenCollection, TokenTree};

/**
 * The data the tokenizer passes share while building one tree.
 */
class TokenizerJob
{
    /**
     * The tokens without whitespace, in source order. Filled by @class(Lexer).
     *
     * @var Token[]
     */
    public array $tokens = [];

    // The root block. Filled by @class(StatementBuilder).
    public Block $root;

    public TokenTree $tree;

    public function __construct(
        public readonly TokenCollection $collection,
    )
    {
    }
}
