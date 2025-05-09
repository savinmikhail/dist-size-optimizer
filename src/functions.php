<?php

declare(strict_types=1);

namespace SavinMikhail\ExportIgnore;

use function count;

function formatBytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
    $pow = min($pow, count($units) - 1);
    $bytes /= (1024 ** $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
}
