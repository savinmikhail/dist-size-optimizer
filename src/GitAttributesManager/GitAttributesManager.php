<?php

declare(strict_types=1);

namespace SavinMikhail\DistSizeOptimizer\GitAttributesManager;

use function sprintf;

final class GitAttributesManager
{
    private const GITATTRIBUTES_FILE = '.gitattributes';
    private const EXPORT_IGNORE_PATTERN = 'export-ignore';

    public function appendPatterns(array $violatingFilesAndDirs): void
    {
        $patterns = $this->generatePatterns(violatingFilesAndDirs: $violatingFilesAndDirs);

        if (empty($patterns)) {
            return;
        }

        $content = $this->readGitAttributes();
        $newContent = $this->appendPatternsToContent(content: $content, patterns: $patterns);
        $this->writeGitAttributes(content: $newContent);
    }

    /**
     * Удаляет из .gitattributes все записанные паттерны export-ignore,
     * которые больше не соответствуют реальным файлам/папкам.
     */
    public function cleanPatterns(): void
    {
        $content = $this->readGitAttributes();
        if ($content === '') {
            // файл не существует или пуст — нечего чистить
            return;
        }

        $lines = preg_split(pattern: '/\r?\n/', subject: $content);
        $kept = [];

        foreach ($lines as $line) {
            $line = trim(string: $line);
            if ($line === '' || !str_contains(haystack: $line, needle: self::EXPORT_IGNORE_PATTERN)) {
                // оставляем пустые и “не-export-ignore” строки
                $kept[] = $line;

                continue;
            }

            // ожидаем формат "<путь> export-ignore"
            [$path] = preg_split(pattern: '/\s+/', subject: $line, limit: 2);
            // убираем возможные кавычки и ведущий слэш
            $path = ltrim(string: $path, characters: '/');

            // проверяем существование относительного пути
            if (file_exists(filename: $path)) {
                $kept[] = $line;
            }
        }

        // собираем обратно, не забывая про переводы строк
        $newContent = implode(separator: "\n", array: array_filter(array: $kept, callback: static fn($l) => $l !== '')) . "\n";
        $this->writeGitAttributes(content: $newContent);
    }

    private function generatePatterns(array $violatingFilesAndDirs): array
    {
        $patterns = [];

        foreach ($violatingFilesAndDirs['files'] as $file) {
            $patterns[] = $this->formatPattern(path: $file);
        }

        foreach ($violatingFilesAndDirs['directories'] as $directory) {
            $patterns[] = $this->formatPattern(path: $directory . '/');
        }

        return array_unique(array: $patterns);
    }

    private function formatPattern(string $path): string
    {
        return sprintf('%s %s', $path, self::EXPORT_IGNORE_PATTERN);
    }

    private function readGitAttributes(): string
    {
        if (!file_exists(filename: self::GITATTRIBUTES_FILE)) {
            return '';
        }

        return file_get_contents(filename: self::GITATTRIBUTES_FILE) ?: '';
    }

    private function appendPatternsToContent(string $content, array $patterns): string
    {
        $existingPatterns = array_filter(array: explode(separator: "\n", string: $content));
        $newPatterns = array_diff($patterns, $existingPatterns);

        if (empty($newPatterns)) {
            return $content;
        }

        if (!empty($content) && !str_ends_with(haystack: $content, needle: "\n")) {
            $content .= "\n";
        }

        return $content . implode(separator: "\n", array: $newPatterns) . "\n";
    }

    private function writeGitAttributes(string $content): void
    {
        file_put_contents(filename: self::GITATTRIBUTES_FILE, data: $content);
    }
}
