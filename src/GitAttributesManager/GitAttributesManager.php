<?php

declare(strict_types=1);

namespace SavinMikhail\ExportIgnore\GitAttributesManager;

class GitAttributesManager
{
    private const GITATTRIBUTES_FILE = '.gitattributes';
    private const EXPORT_IGNORE_PATTERN = 'export-ignore';

    public function appendPatterns(array $violatingFilesAndDirs): void
    {
        $patterns = $this->generatePatterns($violatingFilesAndDirs);
        
        if (empty($patterns)) {
            return;
        }

        $content = $this->readGitAttributes();
        $newContent = $this->appendPatternsToContent($content, $patterns);
        $this->writeGitAttributes($newContent);
    }

    private function generatePatterns(array $violatingFilesAndDirs): array
    {
        $patterns = [];

        foreach ($violatingFilesAndDirs['files'] as $file) {
            $patterns[] = $this->formatPattern($file);
        }

        foreach ($violatingFilesAndDirs['directories'] as $directory) {
            $patterns[] = $this->formatPattern($directory . '/');
        }

        return array_unique($patterns);
    }

    private function formatPattern(string $path): string
    {
        return sprintf('%s %s', $path, self::EXPORT_IGNORE_PATTERN);
    }

    private function readGitAttributes(): string
    {
        if (!file_exists(self::GITATTRIBUTES_FILE)) {
            return '';
        }

        return file_get_contents(self::GITATTRIBUTES_FILE) ?: '';
    }

    private function appendPatternsToContent(string $content, array $patterns): string
    {
        $existingPatterns = array_filter(explode("\n", $content));
        $newPatterns = array_diff($patterns, $existingPatterns);

        if (empty($newPatterns)) {
            return $content;
        }

        if (!empty($content) && !str_ends_with($content, "\n")) {
            $content .= "\n";
        }

        return $content . implode("\n", $newPatterns) . "\n";
    }

    private function writeGitAttributes(string $content): void
    {
        file_put_contents(self::GITATTRIBUTES_FILE, $content);
    }
} 