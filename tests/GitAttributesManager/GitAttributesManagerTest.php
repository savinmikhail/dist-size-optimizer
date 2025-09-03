<?php

declare(strict_types=1);

namespace SavinMikhail\DistSizeOptimizer\Tests\GitAttributesManager;

use PHPUnit\Framework\TestCase;
use SavinMikhail\DistSizeOptimizer\GitAttributesManager\GitAttributesManager;

final class GitAttributesManagerTest extends TestCase
{
    public function testDirectoriesAreAppendedWithSingleSlash(): void
    {
        $tempDir = sys_get_temp_dir() . '/gitattributes_' . uniqid();
        mkdir(directory: $tempDir);
        $cwd = getcwd();
        chdir(directory: $tempDir);

        try {
            $manager = new GitAttributesManager();
            $manager->appendPatterns(violatingFilesAndDirs: ['files' => [], 'directories' => ['/foo/']]);

            $content = file_get_contents(filename: '.gitattributes');
            self::assertSame("/foo/ export-ignore\n", $content);
        } finally {
            chdir(directory: $cwd);
            if (file_exists(filename: $tempDir . '/.gitattributes')) {
                unlink(filename: $tempDir . '/.gitattributes');
            }
            rmdir(directory: $tempDir);
        }
    }
}
