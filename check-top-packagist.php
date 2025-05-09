#!/usr/bin/env php
<?php

declare(strict_types=1);

declare(ticks=1); // ensures signal checks happen between statements

require __DIR__ . '/vendor/autoload.php';

use SavinMikhail\ExportIgnore\Command\CheckCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

const ANALYZED_FILE = __DIR__ . '/var/analyzed.json';
const RESULT_FILE = __DIR__ . '/var/results.json';

$interrupted = false;

pcntl_signal(SIGINT, static function () use (&$interrupted): void {
    echo "\n⛔️ Interrupted. Finishing up...\n";
    $interrupted = true;
});

function loadAnalyzed(): array
{
    if (!file_exists(ANALYZED_FILE)) {
        return [];
    }

    return json_decode(file_get_contents(ANALYZED_FILE), true, 512, JSON_THROW_ON_ERROR);
}

function fetchTopPackages(int $limit = 1000): array
{
    $packages = [];
    $perPage = 100;
    $pages = (int) ceil($limit / $perPage);

    for ($page = 1; $page <= $pages; ++$page) {
        $url = "https://packagist.org/explore/popular.json?per_page={$perPage}&page={$page}";
        $json = file_get_contents($url);

        if (!$json) {
            throw new RuntimeException("Failed to fetch Packagist data for page {$page}");
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $pagePackages = array_map(static fn($pkg) => $pkg['name'], $data['packages']);
        $packages = array_merge($packages, $pagePackages);

        // Stop early if we already have enough
        if (count($packages) >= $limit) {
            break;
        }
    }

    return array_slice($packages, 0, $limit);
}

function runExportIgnoreCheck(string $package): array
{
    $command = new CheckCommand();
    $input = new ArrayInput([
        'package' => $package,
        '--json' => true,
    ]);
    $output = new BufferedOutput();

    try {
        $exitCode = $command->run($input, $output);
        $result = json_decode($output->fetch(), true);

        if ($exitCode === 0) {
            return ['status' => '✅ OK', 'details' => null];
        }

        if (isset($result['files']) && count($result['files']) > 0 || isset($result['directories']) && count($result['directories']) > 0) {
            return ['status' => '❌ Missing export-ignore', 'details' => $result];
        }

        return ['status' => '⚠️ Failed', 'details' => null];
    } catch (Exception $e) {
        return ['status' => '💥 Error: ' . $e->getMessage(), 'details' => null];
    }
}

function saveFailures(array $failures): void
{
    if (count($failures) === 0) {
        echo "\n✅ No export-ignore issues found. Great job, open source!\n";
        return;
    }

    file_put_contents(RESULT_FILE, json_encode($failures, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "\n📝 Saved detailed results to: " . RESULT_FILE . "\n";
}

echo "🔍 Checking top Packagist packages...\n";

$packages = fetchTopPackages(1_000);

$results = [];
$failures = [];

$total = count($packages);
$analyzed = loadAnalyzed();
$newPackages = array_filter($packages, static fn($p) => !isset($analyzed[$p]));

foreach (array_values($newPackages) as $i => $package) {
    if ($interrupted) {
        break;
    }

    echo "📦 [{$i}/{$total}] Checking {$package}...\n";
    $check = runExportIgnoreCheck($package);
    $results[$package] = $check['status'];

    if ($check['status'] === '❌ Missing export-ignore' && isset($check['details'])) {
        $failures[$package] = $check['details'];
    }
}

echo "\n📊 Results:\n";
foreach ($results as $package => $status) {
    echo "  {$package}: {$status}\n";
}

saveFailures($failures);

$allAnalyzed = array_merge($analyzed, $results);
file_put_contents(ANALYZED_FILE, json_encode($allAnalyzed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
