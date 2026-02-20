<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\Exceptions;

use Medas\Core\Exceptions\BaseException;
use Medas\PhpTokenizer\{Statement, Token};

class TokenNotFoundInStatementException extends BaseException
{
    public function __construct(Token $token, Statement $statement)
    {
        parent::__construct($token, $statement);
    }

    public function pattern(): string
    {
        return 'token %s not found in statement %s';
    }
}
