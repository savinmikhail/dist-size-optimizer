#!/usr/bin/env php
<?php

declare(strict_types=1);
declare(ticks = 1); // ensures signal checks happen between statements

require __DIR__ . '/vendor/autoload.php';

const ANALYZED_FILE = __DIR__ . '/var/analyzed.json';

const WORK_DIR = __DIR__ . '/var/.temp-packages';
const CHECK_CMD = __DIR__ . '/bin/export-ignore';
const RESULT_FILE = __DIR__ . '/var/results.json';

mkdir(WORK_DIR, 0777, true);

$interrupted = false;

pcntl_signal(SIGINT, static function () use (&$interrupted) {
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

    for ($page = 1; $page <= $pages; $page++) {
        $url = "https://packagist.org/explore/popular.json?per_page={$perPage}&page={$page}";
        $json = file_get_contents($url);

        if (!$json) {
            throw new RuntimeException("Failed to fetch Packagist data for page {$page}");
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $pagePackages = array_map(fn($pkg) => $pkg['name'], $data['packages']);
        $packages = array_merge($packages, $pagePackages);

        // Stop early if we already have enough
        if (count($packages) >= $limit) {
            break;
        }
    }

    return array_slice($packages, 0, $limit);
}

function installPackage(string $package, int $index, int $total): ?string
{
    $dir = WORK_DIR . '/' . str_replace('/', '__', $package);
    @mkdir($dir, 0777, true);

    echo "📦 [{$index}/{$total}] Installing {$package}...\n";

    $composerJson = <<<JSON
{
    "name": "temp/export-ignore-check",
    "require": {
        "{$package}": "*"
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
JSON;

    file_put_contents("$dir/composer.json", $composerJson);

    exec("cd $dir && composer install --no-interaction --quiet --prefer-dist --no-scripts 2>&1", $output, $exitCode);

    return $exitCode === 0 ? $dir . '/vendor/' . $package : null;
}

function runExportIgnoreCheck(string $vendorPath): array
{
    $cmd = sprintf('%s check %s --json', CHECK_CMD, escapeshellarg($vendorPath));
    exec($cmd, $output, $code);

    if ($code !== 0 && $output !== []) {
        $json = json_decode(implode("\n", $output), true);
        if ($json && (!empty($json['files']) || !empty($json['directories']))) {
            return ['status' => '❌ Missing export-ignore', 'details' => $json];
        }
    }

    return ['status' => $code === 0 ? '✅ OK' : '⚠️ Failed', 'details' => null];
}

function cleanup(): void
{
    exec('rm -rf ' . escapeshellarg(WORK_DIR));
}

function saveFailures(array $failures): void
{
    if (empty($failures)) {
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
    $vendorPath = installPackage($package, $i + 1, $total);

    if (!$vendorPath || !is_dir($vendorPath)) {
        $results[$package] = '💥 Install failed';
        continue;
    }

    $check = runExportIgnoreCheck($vendorPath);
    $results[$package] = $check['status'];

    if ($check['status'] === '❌ Missing export-ignore' && $check['details']) {
        $failures[$package] = $check['details'];
    }
}

cleanup();

echo "\n📊 Results:\n";
foreach ($results as $package => $status) {
    echo "  {$package}: {$status}\n";
}

saveFailures($failures);

$allAnalyzed = array_merge($analyzed, $results);
file_put_contents(ANALYZED_FILE, json_encode($allAnalyzed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
