<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\ClassAnalyser;

class ClassReference
{
    public function __construct(
        public string $label,
        public string $fqn,
    )
    {
    }
}
