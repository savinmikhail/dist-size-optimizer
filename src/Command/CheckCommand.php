<?php

declare(strict_types=1);

namespace SavinMikhail\ExportIgnore\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CheckCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('check')
            ->setDescription('Проверить, какие файлы не удаляются export-ignore')
            ->addArgument('package-path', InputArgument::REQUIRED, 'Путь до папки vendor/xxx/yyy');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root     = rtrim($input->getArgument('package-path'), '/');
        $patterns = require __DIR__ . '/../../export-ignore.php';

        $foundFiles = [];
        $foundDirs  = [];

        foreach ($patterns as $pattern) {
            $globbed = glob($root . '/' . $pattern, GLOB_MARK); // GLOB_MARK добавляет слэш к папкам
            foreach ($globbed as $item) {
                if (is_dir($item)) {
                    $foundDirs[] = $pattern;
                } elseif (is_file($item)) {
                    $foundFiles[] = $pattern;
                }
            }
        }

        if (empty($foundFiles) && empty($foundDirs)) {
            $output->writeln('<info>Ненужные файлы и папки не найдены — всё ок!</info>');
            return Command::SUCCESS;
        }

        if ($foundDirs) {
            $output->writeln('<error>Найдены директории, которые должны быть в export-ignore:</error>');
            foreach ($foundDirs as $d) {
                $output->writeln("  • `{$d}`");
            }
            $output->writeln('');
        }

        if ($foundFiles) {
            $output->writeln('<error>Найдены файлы, которые должны быть в export-ignore:</error>');
            foreach ($foundFiles as $f) {
                $output->writeln("  • `{$f}`");
            }
            $output->writeln('');
        }

        $output->writeln('Добавьте в корень вашего пакета в `.gitattributes` строки вида:');
        foreach (array_merge($foundDirs, $foundFiles) as $path) {
            $output->writeln('  ' . rtrim($path, '/') . "\texport-ignore");
        }

        $totalSize = 0;

        foreach (array_merge($foundDirs, $foundFiles) as $pattern) {
            $matches = glob($root . '/' . $pattern, GLOB_MARK);
            foreach ($matches as $match) {
                $totalSize += $this->getSizeInBytes($match);
            }
        }

        if ($totalSize > 0) {
            $human = $this->formatBytes($totalSize);
            $output->writeln("\n<info>Ваша библиотека станет легче примерно на {$human}, если добавить эти пути в .gitattributes!</info>");
        }

        return Command::FAILURE;
    }

    private function getSizeInBytes(string $path): int
    {
        if (is_file($path)) {
            return filesize($path);
        }

        $size = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $pow = min($pow, count($units) - 1);
        $bytes /= (1024 ** $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}