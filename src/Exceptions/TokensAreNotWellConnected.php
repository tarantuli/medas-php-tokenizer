<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\Exceptions;

use Medas\Core\Exceptions\BaseException;

class TokensAreNotWellConnected extends BaseException
{
    public function __construct(
        string $aText, string|null $aNextText,
        string $bText, string|null $bNextText,

    )
    {
        parent::__construct($aText, $aNextText, $bText, $bNextText);
    }

    public function pattern(): string
    {
        return 'Token A (%s) connects to %s, token B (%s) connects to %s';
    }
}
