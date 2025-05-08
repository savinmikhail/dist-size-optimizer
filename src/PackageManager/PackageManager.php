<?php

declare(strict_types=1);

namespace SavinMikhail\ExportIgnore\PackageManager;

use RuntimeException;

final readonly class PackageManager
{
    private const WORK_DIR = __DIR__ . '/../../var/.temp-packages';

    public function __construct()
    {
        if (!is_dir(self::WORK_DIR)) {
            mkdir(self::WORK_DIR, 0777, true);
        }
    }

    public function downloadPackage(string $packageName): string
    {
        $dir = self::WORK_DIR . '/' . str_replace('/', '__', $packageName);
        @mkdir($dir, 0777, true);

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

        file_put_contents("$dir/composer.json", $composerJson);

        exec("cd $dir && composer install --no-interaction --quiet --prefer-dist --no-scripts 2>&1", $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException("Failed to install package {$packageName}: " . implode("\n", $output));
        }

        $vendorPath = $dir . '/vendor/' . $packageName;
        if (!is_dir($vendorPath)) {
            throw new RuntimeException("Package {$packageName} was not installed correctly");
        }

        return $vendorPath;
    }

    public function cleanup(): void
    {
        exec('rm -rf ' . escapeshellarg(self::WORK_DIR));
    }
} 