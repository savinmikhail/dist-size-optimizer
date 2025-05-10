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
