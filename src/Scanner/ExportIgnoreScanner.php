<?php

declare(strict_types=1);

namespace SavinMikhail\DistSizeOptimizer\Scanner;

use Symfony\Component\Finder\Finder;

use function is_array;

final readonly class ExportIgnoreScanner
{
    /**
     * @param list<string> $patterns
     *
     * @return array{files: list<string>, directories: list<string>}
     */
    public function scan(string $packagePath, array $patterns): array
    {
        $foundFiles = [];
        $foundDirs = [];
        $seenPaths = [];

        foreach ($patterns as $pattern) {
            $fullPath = $packagePath . '/' . $pattern;
            $normalized = rtrim(string: (string) $pattern, characters: '/');

            if (is_dir(filename: $fullPath)) {
                if ($this->alreadySeen(path: $fullPath, seenPaths: $seenPaths)) {
                    continue;
                }

                $foundDirs[] = '/' . $pattern; // we add leading slashes cuz we are working from the project root

                continue;
            }

            if (is_file(filename: $fullPath)) {
                if ($this->alreadySeen(path: $fullPath, seenPaths: $seenPaths)) {
                    continue;
                }

                $foundFiles[] = '/' . $pattern;

                continue;
            }

            $finder = new Finder();
            $finder->depth(levels: '== 0')->in(dirs: $packagePath)->name(patterns: basename(path: (string) $pattern));

            foreach ($finder as $file) {
                if ($this->alreadySeen(path: $file->getPathname(), seenPaths: $seenPaths)) {
                    continue;
                }

                if ($file->isDir()) {
                    $foundDirs[] = '/' . $normalized . '/';
                } else {
                    $foundFiles[] = '/' . $normalized;
                }
            }
        }

        return [
            'files' => array_values(array: array_unique(array: $foundFiles)),
            'directories' => array_values(array: array_unique(array: $foundDirs)),
        ];
    }

    /** @param array<string, true> $seenPaths */
    private function alreadySeen(string $path, array &$seenPaths): bool
    {
        $stats = stat(filename: $path);
        $identity = is_array(value: $stats)
            ? $stats['dev'] . ':' . $stats['ino']
            : (realpath(path: $path) ?: $path);

        if (isset($seenPaths[$identity])) {
            return true;
        }

        $seenPaths[$identity] = true;

        return false;
    }
}
