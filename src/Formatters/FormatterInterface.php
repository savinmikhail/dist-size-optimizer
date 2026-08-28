<?php

declare(strict_types=1);

namespace SavinMikhail\DistSizeOptimizer\Formatters;

use Symfony\Component\Console\Output\OutputInterface;

interface FormatterInterface
{
    /**
     * @param array{files: list<string>, directories: list<string>} $violatingFilesAndDirs
     * @param array<string, int>                                    $pathSizes
     */
    public function output(OutputInterface $output, array $violatingFilesAndDirs, int $totalSizeBytes, string $humanReadableSize, array $pathSizes = []): void;
}
