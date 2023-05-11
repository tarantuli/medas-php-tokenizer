<?php

declare(strict_types=1);

use Medas\PhpTokenizer\PhpTokenizerPackage;
use Medas\ServiceManager\{ServiceConfig, ServiceManager};

chdir(__DIR__);

new ServiceManager(function (): ServiceConfig {
    $config = new ServiceConfig();

    $config->addPackages([
        PhpTokenizerPackage::instance(),
    ]);

    return $config;
});
