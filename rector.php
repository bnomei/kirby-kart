<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/classes',
        __DIR__.'/models',
        __DIR__.'/snippets',
        __DIR__.'/tests',
        __DIR__.'/translations',
    ])
    ->withSkip([
        __DIR__.'/tests/kirby',
    ])
    ->withPhpSets()
    ->withTypeCoverageLevel(0)
    ->withDeadCodeLevel(0)
    ->withCodeQualityLevel(0);
