<?php

declare(strict_types=1);

namespace SavinMikhail\DistSizeOptimizer\Analysis;

use function usort;

final readonly class AnalysisReport
{
    /**
     * @param array<string, array{status: string, packageMetadata: null|array{name: string, version: string, sourceUrl: null|string, sourceReference: null|string, distUrl: null|string, distReference: null|string}, details: null|array{files: string[], directories: string[], suggestions: string[], totalSizeBytes: int, humanReadableSize: string}, error?: string}> $results
     *
     * @return list<array{package: string, packageMetadata: array{name: string, version: string, sourceUrl: null|string, sourceReference: null|string, distUrl: null|string, distReference: null|string}, files: string[], directories: string[], suggestions: string[], totalSizeBytes: int, humanReadableSize: string}>
     */
    public function rankCandidates(array $results): array
    {
        $candidates = [];

        foreach ($results as $package => $result) {
            if ($result['status'] !== 'candidate' || $result['details'] === null || $result['packageMetadata'] === null) {
                continue;
            }

            $candidates[] = [
                'package' => $package,
                'packageMetadata' => $result['packageMetadata'],
                ...$result['details'],
            ];
        }

        usort(
            array: $candidates,
            callback: static fn(array $left, array $right): int => $right['totalSizeBytes'] <=> $left['totalSizeBytes'],
        );

        return $candidates;
    }
}
