<?php

declare(strict_types=1);

use Medas\ConsolePrinter\ConsolePrinterPackage;
use Medas\ObjectInstantiator\ObjectInstantiator;
use Medas\PhpTokenizer\PhpTokenizerPackage;
use Medas\ServiceManager\{ServiceConfig, ServiceManager};

chdir(__DIR__);

new ServiceManager(function (): ServiceConfig {
    $config = new ServiceConfig(ObjectInstantiator::class);

    $config->addPackages([
        PhpTokenizerPackage::instance(),
        ConsolePrinterPackage::instance(),
    ]);

    return $config;
});
