<?php

declare(strict_types=1);

namespace SavinMikhail\DistSizeOptimizer\Formatters;

use Symfony\Component\Console\Output\OutputInterface;

final readonly class ConsoleReportFormatter implements FormatterInterface
{
    /**
     * @param array{files: list<string>, directories: list<string>} $violatingFilesAndDirs
     * @param array<string, int>                                    $pathSizes
     */
    public function output(OutputInterface $output, array $violatingFilesAndDirs, int $totalSizeBytes, string $humanReadableSize, array $pathSizes = []): void
    {
        if (!empty($violatingFilesAndDirs['directories'])) {
            $output->writeln('<error>Directories that should be excluded using export-ignore:</error>');
            foreach ($violatingFilesAndDirs['directories'] as $dir) {
                $output->writeln("  • `{$dir}`");
            }
            $output->writeln('');
        }

        if (!empty($violatingFilesAndDirs['files'])) {
            $output->writeln('<error>Files that should be excluded using export-ignore:</error>');
            foreach ($violatingFilesAndDirs['files'] as $file) {
                $output->writeln("  • `{$file}`");
            }
            $output->writeln('');
        }

        if (!empty($violatingFilesAndDirs['directories']) || !empty($violatingFilesAndDirs['files'])) {
            $output->writeln('To fix this, add the following lines to your `.gitattributes` file:');
            foreach (array_merge($violatingFilesAndDirs['directories'], $violatingFilesAndDirs['files']) as $item) {
                $output->writeln('  ' . rtrim(string: (string) $item, characters: '/') . "\texport-ignore");
            }
        }

        if ($totalSizeBytes > 0) {
            $output->writeln("\n<fg=green>🌿 Your package size could be reduced by approximately <options=bold>{$humanReadableSize}</>!</>");
            $output->writeln("<fg=cyan>🚀 This improves installation time, reduces archive size, and helps CI/CD pipelines.</>\n");
        }
    }
}
