<?php

declare(strict_types=1);

use Medas\ConsolePrinter\ConsolePrinterPackage;
use Medas\ObjectInstantiator\ObjectInstantiator;
use Medas\PhpTokenizer\PhpTokenizerPackage;
use Medas\ServiceManager\{ServiceConfigBuilder, ServiceManager};

chdir(__DIR__);

new ServiceManager(function (): ServiceConfigBuilder {
    $config = new ServiceConfigBuilder(ObjectInstantiator::class);

    $config->addPackages([
        PhpTokenizerPackage::instance(),
        ConsolePrinterPackage::instance(),
    ]);

    return $config;
});
