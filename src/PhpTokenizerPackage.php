<?php

declare(strict_types=1);

namespace Medas\PhpTokenizer;

use Medas\Core\{AsSingleton, BasePackage, Interfaces\ServiceConfigBuilder};

class PhpTokenizerPackage extends BasePackage
{
    use AsSingleton;

    public function dependencies(): array
    {
        return [];
    }

    public function sourceDirectory(): string
    {
        return __DIR__;
    }

    public function initialize(ServiceConfigBuilder $config): void
    {
        require_once __DIR__ . '/GlobalFunctions.php';

        parent::initialize($config);
    }
}
