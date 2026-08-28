<?php

declare(strict_types=1);

namespace SavinMikhail\DistSizeOptimizer\SizeCalculator;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final readonly class FileSizeCalculator
{
    /** @param list<string> $paths */
    public function calculateTotalSize(string $basePath, array $paths): int
    {
        return array_sum(array: $this->calculatePathSizes(basePath: $basePath, paths: $paths));
    }

    /**
     * @param list<string> $paths
     *
     * @return array<string, int>
     */
    public function calculatePathSizes(string $basePath, array $paths): array
    {
        $sizes = [];
        $seenPaths = [];

        foreach ($paths as $path) {
            $fullPath = $basePath . '/' . rtrim(string: $path, characters: '/');
            if (!file_exists(filename: $fullPath)) {
                continue;
            }

            $realPath = realpath(path: $fullPath);
            if ($realPath !== false && isset($seenPaths[$realPath])) {
                continue;
            }

            if ($realPath !== false) {
                $seenPaths[$realPath] = true;
            }

            $sizes[$path] = $this->getSizeInBytes(path: $fullPath);
        }

        return $sizes;
    }

    private function getSizeInBytes(string $path): int
    {
        if (is_file(filename: $path)) {
            $size = filesize(filename: $path);

            return $size === false ? 0 : $size;
        }

        $size = 0;
        foreach (
            new RecursiveIteratorIterator(
                iterator: new RecursiveDirectoryIterator(directory: $path, flags: FilesystemIterator::SKIP_DOTS),
            ) as $file
        ) {
            $size += $file->getSize();
        }

        return $size;
    }
}
