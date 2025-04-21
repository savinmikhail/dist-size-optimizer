#!/usr/bin/env php
<?php

declare(strict_types=1);

const WORK_DIR = __DIR__ . '/.temp-packages';
const CHECK_CMD = __DIR__ . '/bin/export-ignore';
const RESULT_FILE = __DIR__ . '/results.json';

@mkdir(WORK_DIR, 0777, true);

function fetchTopPackages(int $limit = 10): array
{
    $url = "https://packagist.org/explore/popular.json?per_page={$limit}";
    $json = file_get_contents($url);
    if (!$json) {
        throw new RuntimeException('Failed to fetch packagist data');
    }

    $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    return array_map(fn($pkg) => $pkg['name'], $data['packages']);
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

    exec("cd $dir && composer install --no-interaction --quiet 2>&1", $output, $exitCode);

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
$packages = fetchTopPackages(100);

$results = [];
$failures = [];

$total = count($packages);

foreach ($packages as $i => $package) {
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
