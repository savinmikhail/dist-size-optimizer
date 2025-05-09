<?php

declare(strict_types=1);

namespace SavinMikhail\ExportIgnore\PackageManager;

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

        // Get the current git root directory
        exec(command: 'git rev-parse --show-toplevel', output: $output, result_code: $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException(message: 'Not a git repository');
        }
        $gitRoot = trim(string: $output[0]);

        // Create archive
        $archivePath = $dir . '/archive.tar';
        exec(command: "cd {$gitRoot} && git archive --format=tar HEAD -o {$archivePath}", output: $output, result_code: $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException(message: 'Failed to create git archive');
        }

        // Extract archive
        exec(command: "cd {$dir} && tar xf {$archivePath}", output: $output, result_code: $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException(message: 'Failed to extract git archive');
        }

        // Clean up archive file
        unlink(filename: $archivePath);

        return $dir;
    }

    public function cleanup(): void
    {
        exec(command: 'rm -rf ' . escapeshellarg(arg: self::WORK_DIR));
    }
}
