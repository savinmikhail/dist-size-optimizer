<?php

declare(strict_types=1);

namespace SavinMikhail\ExportIgnore\Tests\Command;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SavinMikhail\ExportIgnore\Command\CheckCommand;
use SavinMikhail\ExportIgnore\PackageManager\PackageManager;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class CheckCommandTest extends TestCase
{
    private CheckCommand $command;

    private PackageManager $packageManager;

    private string $testConfigPath;

    protected function setUp(): void
    {
        $this->packageManager = new PackageManager();
        $this->command = new CheckCommand();

        // Create a test config file
        $this->testConfigPath = sys_get_temp_dir() . '/test-export-ignore.php';
        file_put_contents(filename: $this->testConfigPath, data: '<?php return ["tests/", ".gitignore"];');
    }

    protected function tearDown(): void
    {
        $this->packageManager->cleanup();
        if (file_exists(filename: $this->testConfigPath)) {
            unlink(filename: $this->testConfigPath);
        }
    }

    public function testCheckCurrentProject(): void
    {
        $input = new ArrayInput(parameters: [
            '--json' => true,
        ]);
        $output = new BufferedOutput();

        $exitCode = $this->command->run(input: $input, output: $output);

        self::assertNotEquals(0, $exitCode);
        $result = json_decode(json: $output->fetch(), associative: true);
        self::assertIsArray($result);
        self::assertArrayHasKey('files', $result);
        self::assertArrayHasKey('directories', $result);
        self::assertArrayHasKey('totalSizeBytes', $result);
        self::assertArrayHasKey('humanReadableSize', $result);
    }

    public function testCheckPackageByName(): void
    {
        $input = new ArrayInput(parameters: [
            'package' => 'composer/composer',
            '--json' => true,
        ]);
        $output = new BufferedOutput();

        $exitCode = $this->command->run(input: $input, output: $output);

        self::assertNotEquals(0, $exitCode);
        $result = json_decode(json: $output->fetch(), associative: true);
        self::assertIsArray($result);
        self::assertArrayHasKey('files', $result);
        self::assertArrayHasKey('directories', $result);
        self::assertArrayHasKey('totalSizeBytes', $result);
        self::assertArrayHasKey('humanReadableSize', $result);
    }

    public function testCheckInvalidPackageName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Package must be in format vendor/package');

        $input = new ArrayInput(parameters: [
            'package' => 'invalid-package',
        ]);
        $output = new BufferedOutput();

        $this->command->run(input: $input, output: $output);
    }

    public function testCheckWithCustomConfig(): void
    {
        $input = new ArrayInput(parameters: [
            '--json' => true,
            '--config' => $this->testConfigPath,
        ]);
        $output = new BufferedOutput();

        $exitCode = $this->command->run(input: $input, output: $output);

        self::assertNotEquals(0, $exitCode);
        $result = json_decode(json: $output->fetch(), associative: true);
        self::assertIsArray($result);
        self::assertArrayHasKey('files', $result);
        self::assertArrayHasKey('directories', $result);
    }

    public function testCheckWithNonExistentConfig(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Config file not found: /non-existent-config.php');

        $input = new ArrayInput(parameters: [
            '--config' => '/non-existent-config.php',
        ]);
        $output = new BufferedOutput();

        $this->command->run(input: $input, output: $output);
    }
}
