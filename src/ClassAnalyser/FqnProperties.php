<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer\ClassAnalyser;

use Medas\Core\Attributes\Service;

#[Service]
class FqnProperties
{
    public function getFirstPart(string $fqn): string
    {
        return str_contains($fqn, '\\') ? substr($fqn, 0, strpos($fqn, '\\')) : $fqn;
    }

    public function getNextToLastPart(string $fqn): string|null
    {
        if (!str_contains($fqn, '\\')) {
            return null;
        }

        $parts = explode('\\', $fqn);

        return count($parts) >= 2 ? $parts[count($parts) - 2] : null;
    }

    public function getLastPart(string $fqn): string
    {
        return str_contains($fqn, '\\') ? substr($fqn, strrpos($fqn, '\\') + 1) : $fqn;
    }
}
