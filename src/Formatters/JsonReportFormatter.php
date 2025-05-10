<?php

declare(strict_types=1);

namespace SavinMikhail\DistSizeOptimizer\Formatters;

use Symfony\Component\Console\Output\OutputInterface;

final readonly class JsonReportFormatter implements FormatterInterface
{
    public function output(OutputInterface $output, array $violatingFilesAndDirs, int $totalSizeBytes, string $humanReadableSize): void
    {
        $suggestions = array_map(
            callback: static fn(string $path) => rtrim(string: $path, characters: '/') . "\texport-ignore",
            array: array_merge($violatingFilesAndDirs['directories'], $violatingFilesAndDirs['files']),
        );

        $data = [
            'files' => $violatingFilesAndDirs['files'],
            'directories' => $violatingFilesAndDirs['directories'],
            'suggestions' => $suggestions,
            'totalSizeBytes' => $totalSizeBytes,
            'humanReadableSize' => $humanReadableSize,
        ];

        $output->writeln(json_encode(value: $data, flags: JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
