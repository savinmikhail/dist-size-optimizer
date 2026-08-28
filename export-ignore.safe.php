<?php

declare(strict_types=1);

/**
 * Conservative patterns for automated third-party package analysis.
 *
 * Keep documentation and project-specific build files out of this list: they
 * may be unnecessary at runtime, but excluding them should remain a maintainer
 * decision. Every generated candidate still requires source-level review.
 */
return [
    '.github/',
    'tests/',
    'Tests/',

    '.gitignore',
    '.editorconfig',

    'phpcs.xml',
    'phpcs.xml.dist',
    '.php-cs-fixer.dist.php',
    'phpmd.xml.dist',
    'phpstan-baseline.neon',
    'phpstan.neon.dist',
    'phpstan.dist.neon',
    'phpstan.neon',
    'rector.php',
    'phpunit.xml.dist',
    'phpunit.xml',
    'psalm.xml',
    '.psalm/',
    'psalm-baseline.xml',
    'composer-require-checker.json',
    'composer-unused.php',
    'infection.json5',
    'infection.json',
    'infection.json5.dist',
    'infection.json.dist',
    'ecs.php',
];
