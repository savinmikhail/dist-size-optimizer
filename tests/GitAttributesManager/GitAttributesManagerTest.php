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
        mkdir($tempDir);
        $cwd = getcwd();
        chdir($tempDir);

        try {
            $manager = new GitAttributesManager();
            $manager->appendPatterns(['files' => [], 'directories' => ['/foo/']]);

            $content = file_get_contents('.gitattributes');
            self::assertSame("/foo/ export-ignore\n", $content);
        } finally {
            chdir($cwd);
            if (file_exists($tempDir . '/.gitattributes')) {
                unlink($tempDir . '/.gitattributes');
            }
            rmdir($tempDir);
        }
    }
}
