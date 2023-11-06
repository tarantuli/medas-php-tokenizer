<?php

declare(strict_types=1);

// This file should be in the global namespace

use Medas\PhpTokenizer\{Block, BlockDumper, Statement};

function dumpBlock(Block $block): void
{
    service(BlockDumper::class)->dump($block);
}

function dumpStatement(Statement $statement): void
{
    service(BlockDumper::class)->dumpStatement($statement);
}
