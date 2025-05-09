<?php

declare(strict_types=1);

namespace SavinMikhail\ExportIgnore\SizeCalculator;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final readonly class FileSizeCalculator
{
    public function calculateTotalSize(string $basePath, array $paths): int
    {
        $total = 0;

        foreach ($paths as $path) {
            $fullPath = $basePath . '/' . rtrim($path, '/');
            if (!file_exists($fullPath)) {
                continue;
            }

            $total += $this->getSizeInBytes($fullPath);
        }

        return $total;
    }

    private function getSizeInBytes(string $path): int
    {
        if (is_file($path)) {
            return filesize($path);
        }

        $size = 0;
        foreach (
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            ) as $file
        ) {
            $size += $file->getSize();
        }

        return $size;
    }
}
