<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\Exceptions;

use Medas\Core\Exceptions\BaseException;

class PassDependencyNotFound extends BaseException
{
    public function __construct(string $pass, string $dependency)
    {
        parent::__construct($pass, $dependency);
    }

    public function pattern(): string
    {
        return 'tokenizer pass %s runs after %s, which is not a registered tokenizer pass';
    }
}
