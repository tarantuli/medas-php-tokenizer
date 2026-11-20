<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\Exceptions;

use Medas\Core\Exceptions\BaseException;

class TreeIsInconsistent extends BaseException
{
    public function __construct(string $problem, string $tokenText, int $line)
    {
        parent::__construct($problem, $tokenText, $line);
    }

    public function pattern(): string
    {
        return 'the token tree is inconsistent: %s, at "%s" on line %s';
    }
}
