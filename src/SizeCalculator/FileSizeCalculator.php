<?php

declare(strict_types=1);

namespace SavinMikhail\DistSizeOptimizer\SizeCalculator;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final readonly class FileSizeCalculator
{
    public function calculateTotalSize(string $basePath, array $paths): int
    {
        $total = 0;

        foreach ($paths as $path) {
            $fullPath = $basePath . '/' . rtrim(string: (string) $path, characters: '/');
            if (!file_exists(filename: $fullPath)) {
                continue;
            }

            $total += $this->getSizeInBytes(path: $fullPath);
        }

        return $total;
    }

    private function getSizeInBytes(string $path): int
    {
        if (is_file(filename: $path)) {
            return filesize(filename: $path);
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
