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

use function SavinMikhail\DistSizeOptimizer\formatBytes;

const ANALYZED_FILE = __DIR__ . '/var/analyzed.json';
const RESULT_FILE = __DIR__ . '/var/results.json';
const WORKER_DIRECTORY = __DIR__ . '/var/analysis-workers';
const DEFAULT_CONFIG_FILE = __DIR__ . '/export-ignore.discovery.php';
const REVIEW_CONFIG_FILE = __DIR__ . '/export-ignore.review.php';
const ANALYSIS_SCHEMA_VERSION = 3;

$interrupted = false;

pcntl_signal(SIGINT, static function () use (&$interrupted): void {
    echo "\nInterrupted. Saving the completed results...\n";
    $interrupted = true;
});

/** @return array{limit: int, minimumCandidateBytes: int, concurrency: positive-int, resume: bool, config: string} */
function parseOptions(): array
{
    $options = getopt(short_options: '', long_options: ['limit:', 'min-bytes:', 'concurrency:', 'resume', 'config:']);
    $limit = filter_var(value: $options['limit'] ?? 1_000, filter: FILTER_VALIDATE_INT);
    $minimumCandidateBytes = filter_var(value: $options['min-bytes'] ?? 1_024, filter: FILTER_VALIDATE_INT);
    $concurrency = filter_var(value: $options['concurrency'] ?? 4, filter: FILTER_VALIDATE_INT);

    if ($limit === false || $limit < 1) {
        throw new InvalidArgumentException(message: '--limit must be a positive integer');
    }

    if ($minimumCandidateBytes === false || $minimumCandidateBytes < 0) {
        throw new InvalidArgumentException(message: '--min-bytes must be a non-negative integer');
    }

    if ($concurrency === false || $concurrency < 1 || $concurrency > 16) {
        throw new InvalidArgumentException(message: '--concurrency must be between 1 and 16');
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
        'concurrency' => $concurrency,
        'resume' => array_key_exists(key: 'resume', array: $options),
        'config' => realpath(path: $configOption) ?: $configOption,
    ];
}

function configFingerprint(string $config): string
{
    $files = [$config];
    if ($config === realpath(path: DEFAULT_CONFIG_FILE)) {
        $files[] = __DIR__ . '/export-ignore.safe.php';
        $files[] = REVIEW_CONFIG_FILE;
    }

    $contents = '';
    foreach ($files as $file) {
        $fileContents = file_get_contents(filename: $file);
        if ($fileContents === false) {
            throw new RuntimeException(message: "Unable to fingerprint config file: {$file}");
        }

        $contents .= $fileContents;
    }

    return hash(algo: 'sha256', data: ANALYSIS_SCHEMA_VERSION . $contents);
}

/** @return array<string, array{status: string, packageMetadata: mixed, details: mixed, error?: string}> */
function loadAnalyzed(string $fingerprint): array
{
    if (!is_file(filename: ANALYZED_FILE)) {
        return [];
    }

    $contents = file_get_contents(filename: ANALYZED_FILE);

    if (!is_string(value: $contents)) {
        return [];
    }

    $state = json_decode(json: $contents, associative: true, flags: JSON_THROW_ON_ERROR);
    if (!is_array(value: $state) || ($state['configFingerprint'] ?? null) !== $fingerprint) {
        return [];
    }

    $results = $state['results'] ?? null;

    return is_array(value: $results) ? $results : [];
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

/** @return array<string, int> */
function requirePathSizes(mixed $value): array
{
    if (!is_array(value: $value)) {
        throw new RuntimeException(message: 'The checker returned an invalid pathSizes map');
    }

    $sizes = [];
    foreach ($value as $path => $size) {
        if (!is_string(value: $path) || !is_int(value: $size)) {
            throw new RuntimeException(message: 'The checker returned an invalid pathSizes entry');
        }

        $sizes[$path] = $size;
    }

    return $sizes;
}

/**
 * @param array<mixed, mixed> $details
 *
 * @return array{files: string[], directories: string[], suggestions: string[], totalSizeBytes: int, humanReadableSize: string, pathSizes: array<string, int>}
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
        'pathSizes' => requirePathSizes(value: $details['pathSizes'] ?? null),
    ];
}

function requireNullableString(mixed $value, string $field): ?string
{
    if ($value !== null && !is_string(value: $value)) {
        throw new RuntimeException(message: "The worker returned an invalid {$field} value");
    }

    return $value;
}

/** @return null|array{name: string, version: string, sourceUrl: null|string, sourceReference: null|string, distUrl: null|string, distReference: null|string} */
function normalizePackageMetadata(mixed $metadata): ?array
{
    if ($metadata === null) {
        return null;
    }

    if (!is_array(value: $metadata) || !is_string(value: $metadata['name'] ?? null) || !is_string(value: $metadata['version'] ?? null)) {
        throw new RuntimeException(message: 'The worker returned invalid package metadata');
    }

    return [
        'name' => $metadata['name'],
        'version' => $metadata['version'],
        'sourceUrl' => requireNullableString(value: $metadata['sourceUrl'] ?? null, field: 'sourceUrl'),
        'sourceReference' => requireNullableString(value: $metadata['sourceReference'] ?? null, field: 'sourceReference'),
        'distUrl' => requireNullableString(value: $metadata['distUrl'] ?? null, field: 'distUrl'),
        'distReference' => requireNullableString(value: $metadata['distReference'] ?? null, field: 'distReference'),
    ];
}

/**
 * @return array{status: 'ok'|'candidate'|'error', packageMetadata: null|array{name: string, version: string, sourceUrl: null|string, sourceReference: null|string, distUrl: null|string, distReference: null|string}, details: null|array{files: string[], directories: string[], suggestions: string[], totalSizeBytes: int, humanReadableSize: string, pathSizes: array<string, int>}, error?: string}
 */
function normalizeAnalysisResult(mixed $result): array
{
    if (!is_array(value: $result) || !is_string(value: $result['status'] ?? null)) {
        throw new RuntimeException(message: 'The worker returned an invalid result');
    }

    $metadata = normalizePackageMetadata(metadata: $result['packageMetadata'] ?? null);
    $detailsValue = $result['details'] ?? null;
    $details = is_array(value: $detailsValue) ? normalizeDetails(details: $detailsValue) : null;

    return match ($result['status']) {
        'ok' => ['status' => 'ok', 'packageMetadata' => $metadata, 'details' => null],
        'candidate' => ['status' => 'candidate', 'packageMetadata' => $metadata, 'details' => $details],
        'error' => [
            'status' => 'error',
            'packageMetadata' => $metadata,
            'details' => null,
            'error' => is_string(value: $result['error'] ?? null) ? $result['error'] : 'Unknown worker error',
        ],
        default => throw new RuntimeException(message: 'The worker returned an unknown status'),
    };
}

/**
 * @return array{status: 'ok'|'candidate'|'error', packageMetadata: null|array{name: string, version: string, sourceUrl: null|string, sourceReference: null|string, distUrl: null|string, distReference: null|string}, details: null|array{files: string[], directories: string[], suggestions: string[], totalSizeBytes: int, humanReadableSize: string, pathSizes: array<string, int>}, error?: string}
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

    $bytesWritten = file_put_contents(
        filename: $path,
        data: json_encode(value: $data, flags: JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
    );

    if ($bytesWritten === false) {
        throw new RuntimeException(message: "Unable to write JSON file: {$path}");
    }
}

/**
 * @param list<string> $packages
 * @param positive-int $concurrency
 * @param callable(string, array{status: 'ok'|'candidate'|'error', packageMetadata: null|array{name: string, version: string, sourceUrl: null|string, sourceReference: null|string, distUrl: null|string, distReference: null|string}, details: null|array{files: string[], directories: string[], suggestions: string[], totalSizeBytes: int, humanReadableSize: string, pathSizes: array<string, int>}, error?: string}): void $onCompleted
 * @param callable(): bool $shouldStop
 */
function analyzeConcurrently(
    array $packages,
    string $config,
    int $concurrency,
    callable $onCompleted,
    callable $shouldStop,
): void {
    if ($packages === []) {
        return;
    }

    $runDirectory = WORKER_DIRECTORY . '/' . bin2hex(string: random_bytes(length: 8));
    if (!mkdir(directory: $runDirectory, permissions: 0o777, recursive: true) && !is_dir(filename: $runDirectory)) {
        throw new RuntimeException(message: "Unable to create worker directory: {$runDirectory}");
    }

    try {
        foreach (array_chunk(array: $packages, length: $concurrency) as $batch) {
            if ($shouldStop()) {
                break;
            }

            $workers = [];
            foreach ($batch as $package) {
                $resultFile = $runDirectory . '/' . hash(algo: 'sha256', data: $package) . '.json';
                $processId = pcntl_fork();
                if ($processId === -1) {
                    throw new RuntimeException(message: "Unable to fork analysis worker for {$package}");
                }

                if ($processId === 0) {
                    try {
                        writeJson(path: $resultFile, data: ['result' => analyzePackage(package: $package, config: $config)]);
                        exit(0);
                    } catch (Throwable $error) {
                        writeJson(path: $resultFile, data: [
                            'result' => [
                                'status' => 'error',
                                'packageMetadata' => null,
                                'details' => null,
                                'error' => $error->getMessage(),
                            ],
                        ]);
                        exit(1);
                    }
                }

                $workers[$processId] = ['package' => $package, 'resultFile' => $resultFile];
            }

            foreach ($workers as $processId => $worker) {
                pcntl_waitpid(process_id: $processId, status: $status);
                $contents = file_get_contents(filename: $worker['resultFile']);

                if ($contents === false) {
                    $result = [
                        'status' => 'error',
                        'packageMetadata' => null,
                        'details' => null,
                        'error' => "Worker {$processId} produced no result",
                    ];
                } else {
                    $payload = json_decode(json: $contents, associative: true, flags: JSON_THROW_ON_ERROR);
                    $result = normalizeAnalysisResult(result: is_array(value: $payload) ? ($payload['result'] ?? null) : null);
                    unlink(filename: $worker['resultFile']);
                }

                $onCompleted($worker['package'], $result);
            }
        }
    } finally {
        if (is_dir(filename: $runDirectory)) {
            rmdir(directory: $runDirectory);
        }
    }
}

/** @return list<string> */
function loadReviewPatterns(): array
{
    $patterns = require REVIEW_CONFIG_FILE;

    return requireStringList(value: $patterns, field: 'review patterns');
}

function normalizedRulePath(string $path): string
{
    return trim(string: $path, characters: '/');
}

/**
 * @param array<string, mixed> $observation
 * @param list<string>         $reviewPatterns
 *
 * @return array{safePaths: list<string>, reviewRequiredPaths: list<string>, safeSizeBytes: int, reviewRequiredSizeBytes: int}
 */
function classifyObservation(array $observation, array $reviewPatterns): array
{
    $reviewPathMap = [];
    foreach ($reviewPatterns as $pattern) {
        $reviewPathMap[normalizedRulePath(path: $pattern)] = true;
    }

    $safePaths = [];
    $reviewRequiredPaths = [];
    $safeSizeBytes = 0;
    $reviewRequiredSizeBytes = 0;
    $pathSizes = requirePathSizes(value: $observation['pathSizes'] ?? null);
    $paths = [
        ...requireStringList(value: $observation['files'] ?? null, field: 'observation files'),
        ...requireStringList(value: $observation['directories'] ?? null, field: 'observation directories'),
    ];

    foreach ($paths as $path) {
        if (isset($reviewPathMap[normalizedRulePath(path: $path)])) {
            $reviewRequiredPaths[] = $path;
            $reviewRequiredSizeBytes += $pathSizes[$path] ?? 0;
        } else {
            $safePaths[] = $path;
            $safeSizeBytes += $pathSizes[$path] ?? 0;
        }
    }

    return [
        'safePaths' => $safePaths,
        'reviewRequiredPaths' => $reviewRequiredPaths,
        'safeSizeBytes' => $safeSizeBytes,
        'reviewRequiredSizeBytes' => $reviewRequiredSizeBytes,
    ];
}

$options = parseOptions();
$packages = fetchTopPackages(limit: $options['limit']);
$configFingerprint = configFingerprint(config: $options['config']);
$previousResults = $options['resume'] ? loadAnalyzed(fingerprint: $configFingerprint) : [];
$results = $previousResults;
$packagesToAnalyze = array_values(array_filter(
    array: $packages,
    callback: static fn(string $package): bool => !array_key_exists(key: $package, array: $previousResults),
));

echo sprintf(
    "Checking %d popular Packagist packages with %s rules and %d workers...\n",
    count(value: $packagesToAnalyze),
    basename(path: $options['config']),
    $options['concurrency'],
);

$completedCount = 0;
$packagesToAnalyzeCount = count(value: $packagesToAnalyze);
analyzeConcurrently(
    packages: $packagesToAnalyze,
    config: $options['config'],
    concurrency: $options['concurrency'],
    onCompleted: static function (string $package, array $result) use (&$results, &$completedCount, $packagesToAnalyzeCount, $configFingerprint): void {
        ++$completedCount;
        $results[$package] = $result;
        echo sprintf("[%d/%d] %s: %s\n", $completedCount, $packagesToAnalyzeCount, $package, $result['status']);
        writeJson(path: ANALYZED_FILE, data: [
            'configFingerprint' => $configFingerprint,
            'results' => $results,
        ]);
    },
    shouldStop: static fn(): bool => $interrupted,
);

$selectedResults = array_intersect_key($results, array_flip($packages));
$reviewPatterns = loadReviewPatterns();
$observations = array_map(
    callback: static fn(array $observation): array => [
        ...$observation,
        'classification' => classifyObservation(observation: $observation, reviewPatterns: $reviewPatterns),
    ],
    array: new AnalysisReport()->rankCandidates(results: $selectedResults),
);
$candidates = array_values(array_filter(
    array: $observations,
    callback: static fn(array $candidate): bool => $candidate['totalSizeBytes'] >= $options['minimumCandidateBytes'],
));
$safeCandidates = array_values(array_filter(
    array: $observations,
    callback: static fn(array $candidate): bool => $candidate['classification']['safeSizeBytes'] >= $options['minimumCandidateBytes'],
));
usort(
    array: $safeCandidates,
    callback: static fn(array $left, array $right): int => $right['classification']['safeSizeBytes'] <=> $left['classification']['safeSizeBytes'],
);
$reviewCandidates = array_values(array_filter(
    array: $observations,
    callback: static fn(array $candidate): bool => $candidate['classification']['reviewRequiredSizeBytes'] >= $options['minimumCandidateBytes'],
));
usort(
    array: $reviewCandidates,
    callback: static fn(array $left, array $right): int => $right['classification']['reviewRequiredSizeBytes'] <=> $left['classification']['reviewRequiredSizeBytes'],
);
$report = [
    'generatedAt' => gmdate(format: DATE_ATOM),
    'requestedLimit' => $options['limit'],
    'analyzedCount' => count(value: $selectedResults),
    'minimumCandidateBytes' => $options['minimumCandidateBytes'],
    'concurrency' => $options['concurrency'],
    'config' => basename(path: $options['config']),
    'interrupted' => $interrupted,
    'candidates' => $candidates,
    'safeCandidates' => $safeCandidates,
    'reviewCandidates' => $reviewCandidates,
    'observations' => $observations,
    'packages' => $selectedResults,
];
writeJson(path: RESULT_FILE, data: $report);

echo sprintf("\nSaved the reproducible report to %s\n", RESULT_FILE);

if ($safeCandidates === [] && $reviewCandidates === []) {
    echo sprintf(
        "No export-ignore discovery candidates found above %d bytes.\n",
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

if ($safeCandidates !== []) {
    $topSafeCandidate = $safeCandidates[0];
    echo sprintf(
        "Top safe candidate: %s (%s across %d safe paths)\n",
        $topSafeCandidate['package'],
        formatBytes(bytes: $topSafeCandidate['classification']['safeSizeBytes']),
        count(value: $topSafeCandidate['classification']['safePaths']),
    );
}

if ($reviewCandidates !== []) {
    $topReviewCandidate = $reviewCandidates[0];
    echo sprintf(
        "Top review-required candidate: %s (%s across %d paths)\n",
        $topReviewCandidate['package'],
        formatBytes(bytes: $topReviewCandidate['classification']['reviewRequiredSizeBytes']),
        count(value: $topReviewCandidate['classification']['reviewRequiredPaths']),
    );
}
