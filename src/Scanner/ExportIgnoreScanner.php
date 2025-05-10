<?php

declare(strict_types=1);

namespace SavinMikhail\DistSizeOptimizer\Scanner;

use Symfony\Component\Finder\Finder;

final readonly class ExportIgnoreScanner
{
    /**
     * @return array{files: string[], folders: string[]}
     */
    public function scan(string $packagePath, array $patterns): array
    {
        $foundFiles = [];
        $foundDirs = [];

        foreach ($patterns as $pattern) {
            $fullPath = $packagePath . '/' . $pattern;
            $normalized = rtrim(string: (string) $pattern, characters: '/');

            if (is_dir(filename: $fullPath)) {
                $foundDirs[] = '/' . $pattern; // we add leading slashes cuz we are working from the project root

                continue;
            }

            if (is_file(filename: $fullPath)) {
                $foundFiles[] = '/' . $pattern;

                continue;
            }

            $finder = new Finder();
            $finder->depth(levels: '== 0')->in(dirs: $packagePath)->name(patterns: basename(path: (string) $pattern));

            foreach ($finder as $file) {
                if ($file->isDir()) {
                    $foundDirs[] = '/' . $normalized . '/';
                } else {
                    $foundFiles[] = '/' . $normalized;
                }
            }
        }

        return [
            'files' => array_unique(array: $foundFiles),
            'directories' => array_unique(array: $foundDirs),
        ];
    }
}
