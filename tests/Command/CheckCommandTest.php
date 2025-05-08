<?php

declare(strict_types=1);

namespace SavinMikhail\ExportIgnore\Tests\Command;

use PHPUnit\Framework\TestCase;
use SavinMikhail\ExportIgnore\Command\CheckCommand;
use SavinMikhail\ExportIgnore\PackageManager\PackageManager;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class CheckCommandTest extends TestCase
{
    private CheckCommand $command;
    private PackageManager $packageManager;

    protected function setUp(): void
    {
        $this->packageManager = new PackageManager();
        $this->command = new CheckCommand();
    }

    protected function tearDown(): void
    {
        $this->packageManager->cleanup();
    }

    public function testCheckPackageByName(): void
    {
        $input = new ArrayInput([
            'package' => 'composer/composer',
            '--json' => true,
        ]);
        $output = new BufferedOutput();

        $exitCode = $this->command->run($input, $output);

        $this->assertNotEquals(0, $exitCode);
        $result = json_decode($output->fetch(), true);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('files', $result);
        $this->assertArrayHasKey('directories', $result);
        $this->assertArrayHasKey('totalSizeBytes', $result);
        $this->assertArrayHasKey('humanReadableSize', $result);
    }

    public function testCheckInvalidPackageName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Package must be either a valid path or a package name in format vendor/package');

        $input = new ArrayInput([
            'package' => 'invalid-package',
        ]);
        $output = new BufferedOutput();

        $this->command->run($input, $output);
    }
} 