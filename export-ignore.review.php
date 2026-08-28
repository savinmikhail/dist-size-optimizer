<?php

declare(strict_types=1);

/**
 * Non-runtime paths which may still be intentionally shipped for users.
 *
 * These paths belong in discovery reports, but require source-level review
 * before they are included in a pull request.
 */
return [
    'README.md',
    'README.rst',
    'README.txt',
    'CHANGELOG.md',
    'UPGRADE.md',
    'CONTRIBUTING.md',
    'CODE_OF_CONDUCT.md',

    'docs/',
    'doc/',
    'examples/',
    'example/',
    'demo/',
    'demos/',
    'benchmarks/',
    'benchmark/',
    'tools/',
];
