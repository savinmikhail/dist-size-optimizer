<?php

declare(strict_types=1);

namespace SavinMikhail\DistSizeOptimizer\Tests\Scanner;

use PHPUnit\Framework\TestCase;
use SavinMikhail\DistSizeOptimizer\Scanner\ExportIgnoreScanner;

final class ExportIgnoreScannerTest extends TestCase
{
    public function testSameDirectoryIsReportedOnlyOnceAcrossCaseVariants(): void
    {
        $directory = sys_get_temp_dir() . '/dist-size-scanner-' . bin2hex(string: random_bytes(length: 8));
        mkdir(directory: $directory . '/tests', permissions: 0o777, recursive: true);

        try {
            $result = new ExportIgnoreScanner()->scan(packagePath: $directory, patterns: ['tests/', 'Tests/']);

            self::assertCount(1, $result['directories']);
        } finally {
            rmdir(directory: $directory . '/tests');
            rmdir(directory: $directory);
        }
    }
}
