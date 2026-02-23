<?php

declare(strict_types=1);

use Medas\PhpTokenizer\{Block, BlockDumper, Statement};

// This file should be in the global namespace
if (!function_exists('dumpBlock')) {
    function dumpBlock(Block $block): void
    {
        service(BlockDumper::class)->dump($block);
    }

    function dumpStatement(Statement $statement): void
    {
        service(BlockDumper::class)->dumpStatement($statement);
    }
}
