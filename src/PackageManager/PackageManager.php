<?php

declare(strict_types=1);

namespace SavinMikhail\DistSizeOptimizer\PackageManager;

use RuntimeException;

final readonly class PackageManager
{
    private const WORK_DIR = __DIR__ . '/../../var/.temp-packages';

    public function __construct()
    {
        if (!is_dir(filename: self::WORK_DIR)) {
            mkdir(directory: self::WORK_DIR, permissions: 0o777, recursive: true);
        }
    }

    public function downloadPackage(string $packageName): string
    {
        $dir = self::WORK_DIR . '/' . str_replace(search: '/', replace: '__', subject: $packageName);
        @mkdir(directory: $dir, permissions: 0o777, recursive: true);

        $composerJson = <<<JSON
            {
                "name": "temp/export-ignore-check",
                "require": {
                    "{$packageName}": "*"
                },
                "minimum-stability": "stable",
                "prefer-stable": true
            }
            JSON;

        file_put_contents(filename: "{$dir}/composer.json", data: $composerJson);

        exec(command: "cd {$dir} && composer install --no-interaction --quiet --prefer-dist --no-scripts 2>&1", output: $output, result_code: $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException(message: "Failed to install package {$packageName}: " . implode(separator: "\n", array: $output));
        }

        $vendorPath = $dir . '/vendor/' . $packageName;
        if (!is_dir(filename: $vendorPath)) {
            throw new RuntimeException(message: "Package {$packageName} was not installed correctly");
        }

        return $vendorPath;
    }

    public function createGitArchive(): string
    {
        $dir = self::WORK_DIR . '/current-project';
        @mkdir(directory: $dir, permissions: 0o777, recursive: true);

        exec(command: "git archive --format=tar HEAD | tar -x -C {$dir} 2>&1", output: $output, result_code: $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException(message: 'Failed to create git archive: ' . implode(separator: "\n", array: $output));
        }

        return $dir;
    }

    public function cleanup(): void
    {
        if (is_dir(filename: self::WORK_DIR)) {
            exec(command: 'rm -rf ' . escapeshellarg(arg: self::WORK_DIR));
        }
    }
}
