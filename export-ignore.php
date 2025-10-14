<?php

declare(strict_types=1);

return [
    // <<< dirs
    '.github/',
    'tests/',
    'Tests/',
    'docs/',
    'tools/', // often used with bamarni/composer-bin-plugin 
    // dirs >>>
    
    'dist-size-status.json', // used for badge "dist size optimized"
    '.gitignore',
    '.editorconfig',

    'CODE_OF_CONDUCT.md',
    'CONTRIBUTING.md',
    // we won't use CHANGELOG.md here
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

    'Taskfile.yml',
    'Taskfile.yaml',

    'Makefile',

    'bootstrap.php',

    'grumphp.yml',

    'composer.lock',

    'ecs.php',

    'Dockerfile',

    'composer-dependency-analyser.php',

    '.gitlab-ci.yml',

    '.travis.yml',

    '.styleci.yml',

    'UPGRADE-*.md',

    'docker-compose.yaml',
    'docker-compose.yml',
];
