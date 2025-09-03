<?php

declare(strict_types=1);

namespace SavinMikhail\DistSizeOptimizer\Command;

use InvalidArgumentException;
use SavinMikhail\DistSizeOptimizer\Formatters\ConsoleReportFormatter;
use SavinMikhail\DistSizeOptimizer\Formatters\FormatterInterface;
use SavinMikhail\DistSizeOptimizer\Formatters\JsonReportFormatter;
use SavinMikhail\DistSizeOptimizer\GitAttributesManager\GitAttributesManager;
use SavinMikhail\DistSizeOptimizer\PackageManager\PackageManager;
use SavinMikhail\DistSizeOptimizer\Scanner\ExportIgnoreScanner;
use SavinMikhail\DistSizeOptimizer\SizeCalculator\FileSizeCalculator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function SavinMikhail\DistSizeOptimizer\formatBytes;

final class CheckCommand extends Command
{
    private const DEFAULT_CONFIG = __DIR__ . '/../../export-ignore.php';

    public function __construct(
        private readonly ExportIgnoreScanner $scanner = new ExportIgnoreScanner(),
        private readonly FileSizeCalculator $calculator = new FileSizeCalculator(),
        private FormatterInterface $formatter = new ConsoleReportFormatter(),
        private readonly PackageManager $packageManager = new PackageManager(),
        private readonly GitAttributesManager $gitAttributesManager = new GitAttributesManager(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName(name: 'check')
            ->setDescription(description: 'Check which files and folders are not excluded via export-ignore')
            ->addArgument(
                name: 'package',
                mode: InputArgument::OPTIONAL,
                description: 'Package name (e.g. vendor/package). If not provided, checks current project',
            )
            ->addOption(
                name: 'json',
                shortcut: null,
                mode: InputOption::VALUE_NONE,
                description: 'Output results as JSON',
            )
            ->addOption(
                name: 'config',
                shortcut: 'c',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Path to config file with patterns to check',
                default: self::DEFAULT_CONFIG,
            )
            ->addOption(
                name: 'workdir',
                shortcut: 'w',
                mode: InputOption::VALUE_OPTIONAL,
                description: 'Path to project workdir',
            )
            ->addOption(
                name: 'dry-run',
                shortcut: null,
                mode: InputOption::VALUE_NONE,
                description: 'Only show what would be added to .gitattributes without making changes',
            )
            ->addOption(
                name: 'clean',
                shortcut: null,
                mode: InputOption::VALUE_NONE,
                description: 'Clean .gitattributes of non-existent entries',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('clean')) {
            $output->writeln('<info>Cleaning .gitattributes of stale patterns...</info>');
            $this->gitAttributesManager->cleanPatterns();
            $output->writeln('<info>Cleanup complete!</info>');

            return Command::SUCCESS;
        }

        $package = $input->getArgument('package');
        $configPath = $input->getOption('config');
        $isDryRun = $input->getOption('dry-run');
        $workdir = $input->getOption('workdir');

        if (!file_exists(filename: $configPath)) {
            throw new InvalidArgumentException(message: "Config file not found: {$configPath}");
        }

        $this->packageManager->setWorkdir(workdir: $workdir);

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

            $violating = $this->scanner->scan(packagePath: $path, patterns: $patterns);

            if (count(value: $violating['files']) === 0 && count(value: $violating['directories']) === 0) {
                $output->writeln('<info>No unnecessary files or directories found. All good!</info>');

                return Command::SUCCESS;
            }

            $totalSize = $this->calculator->calculateTotalSize(
                basePath: $path,
                paths: array_merge($violating['files'], $violating['directories']),
            );
            $humanSize = formatBytes(bytes: $totalSize);

            if ($input->getOption('json')) {
                $this->formatter = new JsonReportFormatter();
            }
            $this->formatter->output($output, $violating, $totalSize, $humanSize);

            $status = Command::FAILURE;

            if (!$isDryRun && $package === null) {
                $this->gitAttributesManager->appendPatterns(violatingFilesAndDirs: $violating);
                $output->writeln('<info>Patterns have been added to .gitattributes</info>');
                $status = Command::SUCCESS;
            } elseif (!$isDryRun) {
                $output->writeln('<comment>Note: --dry-run is ignored when checking a specific package</comment>');
            }

            return $status;
        } finally {
            $this->packageManager->cleanup();
        }
    }
}
