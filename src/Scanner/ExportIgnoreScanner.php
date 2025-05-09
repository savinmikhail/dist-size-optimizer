<?php

declare(strict_types=1);

namespace SavinMikhail\ExportIgnore\Scanner;

use Symfony\Component\Finder\Finder;

final readonly class ExportIgnoreScanner
{
    public function scan(string $packagePath, array $patterns): array
    {
        $foundFiles = [];
        $foundDirs = [];

        foreach ($patterns as $pattern) {
            $fullPath = $packagePath . '/' . $pattern;
            $normalized = rtrim($pattern, '/');

            if (is_dir($fullPath)) {
                $foundDirs[] = $pattern;

                continue;
            }

            if (is_file($fullPath)) {
                $foundFiles[] = $pattern;

                continue;
            }

            $finder = new Finder();
            $finder->depth('== 0')->in($packagePath)->name(basename($pattern));

            foreach ($finder as $file) {
                if ($file->isDir()) {
                    $foundDirs[] = $normalized . '/';
                } else {
                    $foundFiles[] = $normalized;
                }
            }
        }

        return [
            'files' => array_unique($foundFiles),
            'directories' => array_unique($foundDirs),
        ];
    }
}
