<?php

declare(strict_types=1);

namespace SavinMikhail\DistSizeOptimizer\PackageManager;

use RuntimeException;

use function is_string;

use const DIRECTORY_SEPARATOR;

final class PackageManager
{
    private string $workdir;

    /** @var null|array{name: string, version: string, sourceUrl: null|string, sourceReference: null|string, distUrl: null|string, distReference: null|string} */
    private ?array $packageMetadata = null;

    public function setWorkdir(?string $workdir = null): void
    {
        if (!$workdir) {
            $workdir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dist-size-optimizer-' . bin2hex(string: random_bytes(length: 16));
        }
        $this->workdir = $workdir;

        if (!is_dir(filename: $this->workdir)) {
            mkdir(directory: $this->workdir, permissions: 0o777, recursive: true);
        }
    }

    public function downloadPackage(string $packageName): string
    {
        $this->packageMetadata = null;
        $dir = $this->workdir . '/' . str_replace(search: '/', replace: '__', subject: $packageName);
        @mkdir(directory: $dir, permissions: 0o777, recursive: true);

        $composerJson = <<<JSON
            {
                "name": "temp/export-ignore-check",
                "require": {
                    "{$packageName}": "*"
                },
                "minimum-stability": "dev",
                "prefer-stable": true,
                 "config": {
                    "allow-plugins": false
                }
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

        $this->packageMetadata = $this->readPackageMetadata(lockFile: $dir . '/composer.lock', packageName: $packageName);

        return $vendorPath;
    }

    /** @return null|array{name: string, version: string, sourceUrl: null|string, sourceReference: null|string, distUrl: null|string, distReference: null|string} */
    public function getPackageMetadata(): ?array
    {
        return $this->packageMetadata;
    }

    public function createGitArchive(): string
    {
        $dir = $this->workdir . '/current-project';
        @mkdir(directory: $dir, permissions: 0o777, recursive: true);

        exec(command: "git archive --format=tar HEAD | tar -x -C {$dir} 2>&1", output: $output, result_code: $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException(message: 'Failed to create git archive: ' . implode(separator: "\n", array: $output));
        }

        return $dir;
    }

    public function cleanup(): void
    {
        if (is_dir(filename: $this->workdir)) {
            exec(command: 'rm -rf ' . escapeshellarg(arg: $this->workdir));
        }
    }

    /** @return array{name: string, version: string, sourceUrl: null|string, sourceReference: null|string, distUrl: null|string, distReference: null|string} */
    private function readPackageMetadata(string $lockFile, string $packageName): array
    {
        $contents = file_get_contents(filename: $lockFile);
        if ($contents === false) {
            throw new RuntimeException(message: "Unable to read Composer lock file for {$packageName}");
        }

        $lock = json_decode(json: $contents, associative: true, flags: JSON_THROW_ON_ERROR);
        foreach ($lock['packages'] ?? [] as $package) {
            if (($package['name'] ?? null) !== $packageName) {
                continue;
            }

            $version = $package['version'] ?? null;
            if (!is_string(value: $version)) {
                throw new RuntimeException(message: "Composer lock file has no version for {$packageName}");
            }

            return [
                'name' => $packageName,
                'version' => $version,
                'sourceUrl' => $this->nullableString(value: $package['source']['url'] ?? null),
                'sourceReference' => $this->nullableString(value: $package['source']['reference'] ?? null),
                'distUrl' => $this->nullableString(value: $package['dist']['url'] ?? null),
                'distReference' => $this->nullableString(value: $package['dist']['reference'] ?? null),
            ];
        }

        throw new RuntimeException(message: "Composer lock file has no metadata for {$packageName}");
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string(value: $value) ? $value : null;
    }
}
