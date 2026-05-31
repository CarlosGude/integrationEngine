<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        // Base moderna y segura (sin Symfony opinionado)
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => true,

        // PHPUnit moderno (attributes-first)
        'php_unit_attributes' => true,
        'php_unit_test_annotation' => false,
        'php_unit_internal_class' => false,

        // Evita docblocks heredados de PHPUnit legacy
        'phpdoc_to_comment' => false,

        // Mantener consistencia en tests sin ruido
        'php_unit_method_casing' => true,
    ])
    ->setFinder(
        (new Finder())
            ->in(__DIR__)
            ->exclude([
                'vendor',
                'var',
                'cache',
            ])
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
    );