#!/usr/bin/env php
<?php

declare(strict_types=1);

declare(ticks=1);

require __DIR__ . '/vendor/autoload.php';

use SavinMikhail\DistSizeOptimizer\Analysis\AnalysisReport;
use SavinMikhail\DistSizeOptimizer\Command\CheckCommand;
use SavinMikhail\DistSizeOptimizer\PackageManager\PackageManager;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

const ANALYZED_FILE = __DIR__ . '/var/analyzed.json';
const RESULT_FILE = __DIR__ . '/var/results.json';
const DEFAULT_CONFIG_FILE = __DIR__ . '/export-ignore.safe.php';

$interrupted = false;

pcntl_signal(SIGINT, static function () use (&$interrupted): void {
    echo "\nInterrupted. Saving the completed results...\n";
    $interrupted = true;
});

/** @return array{limit: int, minimumCandidateBytes: int, resume: bool, config: string} */
function parseOptions(): array
{
    $options = getopt(short_options: '', long_options: ['limit:', 'min-bytes:', 'resume', 'config:']);
    $limit = filter_var(value: $options['limit'] ?? 1_000, filter: FILTER_VALIDATE_INT);
    $minimumCandidateBytes = filter_var(value: $options['min-bytes'] ?? 1_024, filter: FILTER_VALIDATE_INT);

    if ($limit === false || $limit < 1) {
        throw new InvalidArgumentException(message: '--limit must be a positive integer');
    }

    if ($minimumCandidateBytes === false || $minimumCandidateBytes < 0) {
        throw new InvalidArgumentException(message: '--min-bytes must be a non-negative integer');
    }

    $configOption = $options['config'] ?? DEFAULT_CONFIG_FILE;
    if (!is_string(value: $configOption)) {
        throw new InvalidArgumentException(message: '--config must be a file path');
    }

    if (!is_file(filename: $configOption)) {
        throw new InvalidArgumentException(message: "Config file not found: {$configOption}");
    }

    return [
        'limit' => $limit,
        'minimumCandidateBytes' => $minimumCandidateBytes,
        'resume' => array_key_exists(key: 'resume', array: $options),
        'config' => realpath(path: $configOption) ?: $configOption,
    ];
}

/** @return array<string, array{status: string, packageMetadata: mixed, details: mixed, error?: string}> */
function loadAnalyzed(): array
{
    if (!is_file(filename: ANALYZED_FILE)) {
        return [];
    }

    $contents = file_get_contents(filename: ANALYZED_FILE);

    return is_string(value: $contents)
        ? json_decode(json: $contents, associative: true, flags: JSON_THROW_ON_ERROR)
        : [];
}

/** @return list<string> */
function fetchTopPackages(int $limit): array
{
    $packages = [];
    $perPage = min(100, $limit);
    $pages = (int) ceil($limit / $perPage);

    for ($page = 1; $page <= $pages; ++$page) {
        $url = "https://packagist.org/explore/popular.json?per_page={$perPage}&page={$page}";
        $json = file_get_contents(filename: $url);

        if ($json === false) {
            throw new RuntimeException(message: "Failed to fetch Packagist data for page {$page}");
        }

        $data = json_decode(json: $json, associative: true, flags: JSON_THROW_ON_ERROR);
        $pagePackages = array_map(
            callback: static fn(array $package): string => $package['name'],
            array: $data['packages'],
        );
        $packages = array_merge($packages, $pagePackages);

        if (count(value: $packages) >= $limit) {
            break;
        }
    }

    return array_values(array: array_slice(array: $packages, offset: 0, length: $limit));
}

/** @return list<string> */
function requireStringList(mixed $value, string $field): array
{
    if (!is_array(value: $value)) {
        throw new RuntimeException(message: "The checker returned an invalid {$field} list");
    }

    $strings = [];
    foreach ($value as $item) {
        if (!is_string(value: $item)) {
            throw new RuntimeException(message: "The checker returned a non-string {$field} entry");
        }

        $strings[] = $item;
    }

    return $strings;
}

/**
 * @param array<mixed, mixed> $details
 *
 * @return array{files: string[], directories: string[], suggestions: string[], totalSizeBytes: int, humanReadableSize: string}
 */
function normalizeDetails(array $details): array
{
    $totalSizeBytes = $details['totalSizeBytes'] ?? null;
    $humanReadableSize = $details['humanReadableSize'] ?? null;

    if (!is_int(value: $totalSizeBytes) || !is_string(value: $humanReadableSize)) {
        throw new RuntimeException(message: 'The checker returned invalid size information');
    }

    return [
        'files' => requireStringList(value: $details['files'] ?? null, field: 'files'),
        'directories' => requireStringList(value: $details['directories'] ?? null, field: 'directories'),
        'suggestions' => requireStringList(value: $details['suggestions'] ?? null, field: 'suggestions'),
        'totalSizeBytes' => $totalSizeBytes,
        'humanReadableSize' => $humanReadableSize,
    ];
}

/**
 * @return array{status: 'ok'|'candidate'|'error', packageMetadata: null|array{name: string, version: string, sourceUrl: null|string, sourceReference: null|string, distUrl: null|string, distReference: null|string}, details: null|array{files: string[], directories: string[], suggestions: string[], totalSizeBytes: int, humanReadableSize: string}, error?: string}
 */
function analyzePackage(string $package, string $config): array
{
    $input = new ArrayInput(parameters: [
        'package' => $package,
        '--json' => true,
        '--dry-run' => true,
        '--config' => $config,
    ]);
    $output = new BufferedOutput();
    $packageManager = new PackageManager();

    try {
        $exitCode = new CheckCommand(packageManager: $packageManager)->run(input: $input, output: $output);
        $packageMetadata = $packageManager->getPackageMetadata();
        if ($exitCode === 0) {
            return ['status' => 'ok', 'packageMetadata' => $packageMetadata, 'details' => null];
        }

        $details = json_decode(json: $output->fetch(), associative: true, flags: JSON_THROW_ON_ERROR);
        if (!is_array(value: $details)) {
            throw new RuntimeException(message: 'The checker returned an invalid JSON report');
        }

        return [
            'status' => 'candidate',
            'packageMetadata' => $packageMetadata,
            'details' => normalizeDetails(details: $details),
        ];
    } catch (Throwable $error) {
        return [
            'status' => 'error',
            'packageMetadata' => null,
            'details' => null,
            'error' => $error->getMessage(),
        ];
    }
}

/** @param array<string, mixed> $data */
function writeJson(string $path, array $data): void
{
    $directory = dirname(path: $path);
    if (!is_dir(filename: $directory)) {
        mkdir(directory: $directory, permissions: 0o777, recursive: true);
    }

    file_put_contents(
        filename: $path,
        data: json_encode(value: $data, flags: JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
    );
}

$options = parseOptions();
$packages = fetchTopPackages(limit: $options['limit']);
$previousResults = $options['resume'] ? loadAnalyzed() : [];
$results = $previousResults;
$packagesToAnalyze = array_values(array_filter(
    array: $packages,
    callback: static fn(string $package): bool => !array_key_exists(key: $package, array: $previousResults),
));

echo sprintf(
    "Checking %d popular Packagist packages with %s rules...\n",
    count(value: $packagesToAnalyze),
    basename(path: $options['config']),
);

foreach ($packagesToAnalyze as $index => $package) {
    if ($interrupted) {
        break;
    }

    echo sprintf("[%d/%d] %s\n", $index + 1, count(value: $packagesToAnalyze), $package);
    $results[$package] = analyzePackage(package: $package, config: $options['config']);
    writeJson(path: ANALYZED_FILE, data: $results);
}

$selectedResults = array_intersect_key($results, array_flip($packages));
$observations = new AnalysisReport()->rankCandidates(results: $selectedResults);
$candidates = array_values(array_filter(
    array: $observations,
    callback: static fn(array $candidate): bool => $candidate['totalSizeBytes'] >= $options['minimumCandidateBytes'],
));
$report = [
    'generatedAt' => gmdate(format: DATE_ATOM),
    'requestedLimit' => $options['limit'],
    'analyzedCount' => count(value: $selectedResults),
    'minimumCandidateBytes' => $options['minimumCandidateBytes'],
    'config' => basename(path: $options['config']),
    'interrupted' => $interrupted,
    'candidates' => $candidates,
    'observations' => $observations,
    'packages' => $selectedResults,
];
writeJson(path: RESULT_FILE, data: $report);

echo sprintf("\nSaved the reproducible report to %s\n", RESULT_FILE);

if ($candidates === []) {
    echo sprintf(
        "No conservative export-ignore candidates found above %d bytes.\n",
        $options['minimumCandidateBytes'],
    );

    if ($observations !== []) {
        echo sprintf(
            "Largest observation below the threshold: %s (%s)\n",
            $observations[0]['package'],
            $observations[0]['humanReadableSize'],
        );
    }

    exit(0);
}

$topCandidate = $candidates[0];
echo sprintf(
    "Top candidate: %s (%s across %d paths)\n",
    $topCandidate['package'],
    $topCandidate['humanReadableSize'],
    count(value: $topCandidate['files']) + count(value: $topCandidate['directories']),
);
