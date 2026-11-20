<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\Exceptions;

use Medas\Core\Exceptions\BaseException;

class CircularPassDependency extends BaseException
{
    /**
     * @param string[] $chain
     */
    public function __construct(array $chain, string $pass)
    {
        parent::__construct(implode(' → ', $chain), $pass);
    }

    public function pattern(): string
    {
        return 'tokenizer passes depend on each other in a circle: %s → %s';
    }
}
