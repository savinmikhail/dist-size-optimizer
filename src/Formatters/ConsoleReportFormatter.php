<?php

declare(strict_types=1);

namespace SavinMikhail\ExportIgnore\Formatters;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class ConsoleReportFormatter
{
    public function output(OutputInterface $output, array $result, int $totalSizeBytes, string $humanReadableSize): void
    {
        if (!empty($result['directories'])) {
            $output->writeln('<error>Directories that should be excluded using export-ignore:</error>');
            foreach ($result['directories'] as $dir) {
                $output->writeln("  • `{$dir}`");
            }
            $output->writeln('');
        }

        if (!empty($result['files'])) {
            $output->writeln('<error>Files that should be excluded using export-ignore:</error>');
            foreach ($result['files'] as $file) {
                $output->writeln("  • `{$file}`");
            }
            $output->writeln('');
        }

        $output->writeln('To fix this, add the following lines to your `.gitattributes` file:');
        foreach (array_merge($result['directories'], $result['files']) as $item) {
            $output->writeln('  ' . rtrim($item, '/') . "\texport-ignore");
        }

        if ($totalSizeBytes > 0) {
            $output->writeln("\n<fg=green>🌿 Your package size could be reduced by approximately <options=bold>{$humanReadableSize}</>!</>");
            $output->writeln("<fg=cyan>🚀 This improves installation time, reduces archive size, and helps CI/CD pipelines.</>\n");
        }
    }
}