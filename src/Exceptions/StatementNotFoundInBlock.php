<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\Exceptions;

use Medas\Core\Exceptions\BaseException;
use Medas\PhpTokenizer\{Block, Statement};

class StatementNotFoundInBlock extends BaseException
{
    public function __construct(Statement|Block $statement, Block $block)
    {
        parent::__construct($statement::class, $block->depth);
    }

    public function pattern(): string
    {
        return '%s not found in the block at depth %s';
    }
}
