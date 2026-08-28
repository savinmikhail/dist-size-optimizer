<?php

declare(strict_types=1);

/**
 * Conservative patterns for automated third-party package analysis.
 *
 * Every generated candidate still requires source-level review.
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

    'composer.lock',
    'Makefile',
    'Taskfile.yml',
    'Taskfile.yaml',
    'Dockerfile',
    '.dockerignore',
    'docker-compose.yml',
    'docker-compose.yaml',
    'compose.yml',
    'compose.yaml',
    '.gitlab-ci.yml',
    '.travis.yml',
    '.styleci.yml',
];
