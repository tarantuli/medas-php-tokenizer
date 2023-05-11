<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\Contexts;

use Medas\Core\AsSingleton;

class ClassDeclaration implements Context
{
    use AsSingleton;

    public function __toString(): string
    {
        return 'CD';
    }
}
