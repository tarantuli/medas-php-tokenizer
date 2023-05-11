<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\StatementTypes;

interface StatementType extends \Stringable
{
    public static function instance(): self;
}
