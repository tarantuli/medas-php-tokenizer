<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\Passes;

/**
 * One step of building a token tree. Every #[Service] implementing this interface is run by
 * @class(\Medas\PhpTokenizer\TreeBuilder), in an order that follows ```runsAfter()```. Other packages can add passes.
 */
interface TokenizerPass
{
    /**
     * The passes whose results this pass reads. They run before this pass.
     *
     * @return class-string<TokenizerPass>[]
     */
    public function runsAfter(): array;

    public function run(TokenizerJob $job): void;
}
