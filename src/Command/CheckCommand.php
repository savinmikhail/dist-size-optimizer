<?php

declare(strict_types=1);

namespace SavinMikhail\ExportIgnore\Command;

use SavinMikhail\ExportIgnore\Formatters\ConsoleReportFormatter;
use SavinMikhail\ExportIgnore\Formatters\JsonReportFormatter;
use SavinMikhail\ExportIgnore\PackageManager\PackageManager;
use SavinMikhail\ExportIgnore\Scanner\ExportIgnoreScanner;
use SavinMikhail\ExportIgnore\SizeCalculator\FileSizeCalculator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use function SavinMikhail\ExportIgnore\formatBytes;

final class CheckCommand extends Command
{
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
            ->setName('check')
            ->setDescription('Check which files and folders are not excluded via export-ignore')
            ->addArgument('package', InputArgument::OPTIONAL, 'Package name (e.g. vendor/package). If not provided, checks current project')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output results as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $package = $input->getArgument('package');
        
        try {
            if ($package === null) {
                $path = $this->packageManager->createGitArchive();
            } else {
                if (!str_contains($package, '/')) {
                    throw new \InvalidArgumentException('Package must be in format vendor/package');
                }
                $path = $this->packageManager->downloadPackage($package);
            }

            $patterns = require __DIR__ . '/../../export-ignore.php';

            $result = $this->scanner->scan($path, $patterns);

            if (empty($result['files']) && empty($result['directories'])) {
                $output->writeln('<info>No unnecessary files or directories found. All good!</info>');
                return Command::SUCCESS;
            }

            $totalSize = $this->calculator->calculateTotalSize($path, array_merge($result['files'], $result['directories']));
            $humanSize = formatBytes($totalSize);

            if ($input->getOption('json')) {
                $this->jsonFormatter->output($output, $result, $totalSize, $humanSize);
            } else {
                $this->consoleFormatter->output($output, $result, $totalSize, $humanSize);
            }

            return Command::FAILURE;
        } finally {
            $this->packageManager->cleanup();
        }
    }
}
