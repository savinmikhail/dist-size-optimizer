<?php

declare(strict_types=1);

namespace SavinMikhail\ExportIgnore\Formatters;

use Symfony\Component\Console\Output\OutputInterface;

final readonly class JsonReportFormatter
{
    public function output(OutputInterface $output, array $result, int $totalSizeBytes, string $humanReadableSize): void
    {
        $suggestions = array_map(
            static fn(string $path) => rtrim($path, '/') . "\texport-ignore",
            array_merge($result['directories'], $result['files']),
        );

        $data = [
            'files' => $result['files'],
            'directories' => $result['directories'],
            'suggestions' => $suggestions,
            'totalSizeBytes' => $totalSizeBytes,
            'humanReadableSize' => $humanReadableSize,
        ];

        $output->writeln(json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
