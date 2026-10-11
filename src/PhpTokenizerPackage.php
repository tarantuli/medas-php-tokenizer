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

    /**
     * The additional token constants are used in class constants (e.g. in StructureFinder), which PHP evaluates when
     * the first instance is created. Services are injected as lazy objects, so the constructors that also define them
     * may not have run by then: define them up front instead.
     */
    public function ready(): void
    {
        parent::ready();

        new AdditionalTokensDefiner()->define();
    }
}
