<?php

declare(strict_types=1);

namespace SavinMikhail\DistSizeOptimizer\Tests\Command;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SavinMikhail\DistSizeOptimizer\Command\CheckCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class CheckCommandTest extends TestCase
{
    private CheckCommand $command;

    private string $testConfigPath;

    protected function setUp(): void
    {
        $this->command = new CheckCommand();

        // Create a test config file
        $this->testConfigPath = sys_get_temp_dir() . '/test-export-ignore.php';
        file_put_contents(filename: $this->testConfigPath, data: '<?php return ["README.md"];');
    }

    protected function tearDown(): void
    {
        if (file_exists(filename: $this->testConfigPath)) {
            unlink(filename: $this->testConfigPath);
        }
    }

    public function testCheckCurrentProject(): void
    {
        $input = new ArrayInput(parameters: [
            '--json' => true,
            '--config' => $this->testConfigPath,
            '--dry-run' => true,
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
            'package' => 'symfony/console',
            '--json' => true,
            '--config' => $this->testConfigPath,
            '--dry-run' => true,
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

    public function testInvalidPackageName(): void
    {
        $input = new ArrayInput(parameters: [
            'package' => 'invalid-package',
        ]);
        $output = new BufferedOutput();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Package must be in format vendor/package');

        $this->command->run(input: $input, output: $output);
    }

    public function testInvalidConfigPath(): void
    {
        $input = new ArrayInput(parameters: [
            '--config' => '/non-existent/path.php',
        ]);
        $output = new BufferedOutput();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Config file not found: /non-existent/path.php');

        $this->command->run(input: $input, output: $output);
    }
}
