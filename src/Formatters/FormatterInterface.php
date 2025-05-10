<?php

declare(strict_types=1);

namespace SavinMikhail\DistSizeOptimizer\Formatters;

use Symfony\Component\Console\Output\OutputInterface;

interface FormatterInterface
{
    public function output(OutputInterface $output, array $violatingFilesAndDirs, int $totalSizeBytes, string $humanReadableSize): void;
}
