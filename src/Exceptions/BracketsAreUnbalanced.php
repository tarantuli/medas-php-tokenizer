<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\Exceptions;

use Medas\Core\Exceptions\BaseException;

class BracketsAreUnbalanced extends BaseException
{
    public function __construct(string $bracket, int $line)
    {
        parent::__construct($bracket, $line);
    }

    public function pattern(): string
    {
        return 'bracket %s on line %s has no matching bracket';
    }
}
