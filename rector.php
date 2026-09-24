<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php81\Rector\Array_\ArrayToFirstClassCallableRector;
use Rector\TypeDeclaration\Rector\ClassMethod\StrictArrayParamDimFetchRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/config',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/src/Console/stubs',
        // Mis-types the service container ($app['cache']) as `array` — it is ArrayAccess.
        StrictArrayParamDimFetchRector::class,
        // Config values must stay [Class, 'method'] arrays so `config:cache` can serialise them.
        ArrayToFirstClassCallableRector::class => [__DIR__ . '/tests/Feature/ExecutionConsistencyTest.php'],
    ])
    ->withPhpSets(php82: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    )
    ->withImportNames(importShortClasses: false, removeUnusedImports: true);
