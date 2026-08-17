<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/AuthBundle.php',
        __DIR__.'/DependencyInjection',
        __DIR__.'/Entity',
        __DIR__.'/Factory',
        __DIR__.'/Manager',
        __DIR__.'/Repository',
        __DIR__.'/Tests',
    ])
    // No argument: the target PHP version is read from the "php" constraint in
    // composer.json, so the rule set follows the bundle instead of drifting.
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
        doctrineCodeQuality: true,
        symfonyCodeQuality: true,
    )
    ->withAttributesSets(symfony: true, doctrine: true, phpunit: true)
    ->withComposerBased(doctrine: true, symfony: true, phpunit: true)
    ->withSkip([
        // Pure helpers are deliberately static: it documents that they touch no state.
        LocallyCalledStaticMethodToNonStaticRector::class,
        // Doctrine entities keep their mapped properties out of the constructor.
        ClassPropertyAssignToConstructorPromotionRector::class => [
            __DIR__.'/Entity',
        ],
    ])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true);
