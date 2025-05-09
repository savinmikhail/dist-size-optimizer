<?php

declare(strict_types=1);

namespace SavinMikhail\ExportIgnore\Command;

use InvalidArgumentException;
use SavinMikhail\ExportIgnore\Formatters\ConsoleReportFormatter;
use SavinMikhail\ExportIgnore\Formatters\JsonReportFormatter;
use SavinMikhail\ExportIgnore\PackageManager\PackageManager;
use SavinMikhail\ExportIgnore\Scanner\ExportIgnoreScanner;
use SavinMikhail\ExportIgnore\SizeCalculator\FileSizeCalculator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function SavinMikhail\ExportIgnore\formatBytes;

final class CheckCommand extends Command
{
    private const DEFAULT_CONFIG = __DIR__ . '/../../export-ignore.php';

    public function __construct(
        private readonly ExportIgnoreScanner $scanner = new ExportIgnoreScanner(),
        private readonly FileSizeCalculator $calculator = new FileSizeCalculator(),
        private readonly JsonReportFormatter $jsonFormatter = new JsonReportFormatter(),
        private readonly ConsoleReportFormatter $consoleFormatter = new ConsoleReportFormatter(),
        private readonly PackageManager $packageManager = new PackageManager(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName(name: 'check')
            ->setDescription(description: 'Check which files and folders are not excluded via export-ignore')
            ->addArgument(name: 'package', mode: InputArgument::OPTIONAL, description: 'Package name (e.g. vendor/package). If not provided, checks current project')
            ->addOption(name: 'json', shortcut: null, mode: InputOption::VALUE_NONE, description: 'Output results as JSON')
            ->addOption(name: 'config', shortcut: 'c', mode: InputOption::VALUE_REQUIRED, description: 'Path to config file with patterns to check', default: self::DEFAULT_CONFIG);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $package = $input->getArgument('package');
        $configPath = $input->getOption('config');

        if (!file_exists(filename: $configPath)) {
            throw new InvalidArgumentException(message: "Config file not found: {$configPath}");
        }

        try {
            if ($package === null) {
                $path = $this->packageManager->createGitArchive();
            } else {
                if (!str_contains(haystack: (string) $package, needle: '/')) {
                    throw new InvalidArgumentException(message: 'Package must be in format vendor/package');
                }
                $path = $this->packageManager->downloadPackage(packageName: $package);
            }

            $patterns = require $configPath;

            $result = $this->scanner->scan(packagePath: $path, patterns: $patterns);

            if (count(value: $result['files']) === 0 && count(value: $result['directories']) === 0) {
                $output->writeln('<info>No unnecessary files or directories found. All good!</info>');

                return Command::SUCCESS;
            }

            $totalSize = $this->calculator->calculateTotalSize(basePath: $path, paths: array_merge($result['files'], $result['directories']));
            $humanSize = formatBytes(bytes: $totalSize);

            if ($input->getOption('json')) {
                $this->jsonFormatter->output(output: $output, result: $result, totalSizeBytes: $totalSize, humanReadableSize: $humanSize);
            } else {
                $this->consoleFormatter->output(output: $output, result: $result, totalSizeBytes: $totalSize, humanReadableSize: $humanSize);
            }

            return Command::FAILURE;
        } finally {
            $this->packageManager->cleanup();
        }
    }
}
