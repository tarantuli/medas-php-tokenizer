<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\StatementTypes;

use Medas\Core\AsSingleton;

class EnumCase implements StatementType
{
    use AsSingleton;

    public function __toString(): string
    {
        return 'enum case';
    }
}
