<?php

declare(strict_types=1);

namespace SavinMikhail\DistSizeOptimizer\Tests\Analysis;

use PHPUnit\Framework\TestCase;
use SavinMikhail\DistSizeOptimizer\Analysis\AnalysisReport;

final class AnalysisReportTest extends TestCase
{
    public function testCandidatesAreRankedByPotentialSavings(): void
    {
        $results = [
            'vendor/small' => $this->candidate(size: 100),
            'vendor/clean' => ['status' => 'ok', 'packageMetadata' => $this->metadata(), 'details' => null],
            'vendor/large' => $this->candidate(size: 1_000),
            'vendor/error' => ['status' => 'error', 'packageMetadata' => null, 'details' => null, 'error' => 'Failed'],
        ];

        $candidates = new AnalysisReport()->rankCandidates(results: $results);

        self::assertSame(['vendor/large', 'vendor/small'], array_column(array: $candidates, column_key: 'package'));
        self::assertSame([1_000, 100], array_column(array: $candidates, column_key: 'totalSizeBytes'));
    }

    public function testResultsWithoutPackageMetadataAreNotActionable(): void
    {
        $result = $this->candidate(size: 1_000);
        $result['packageMetadata'] = null;

        self::assertSame([], new AnalysisReport()->rankCandidates(results: ['vendor/package' => $result]));
    }

    /** @return array{status: string, packageMetadata: array{name: string, version: string, sourceUrl: string, sourceReference: string, distUrl: null, distReference: null}, details: array{files: string[], directories: string[], suggestions: string[], totalSizeBytes: int, humanReadableSize: string, pathSizes: array<string, int>}} */
    private function candidate(int $size): array
    {
        return [
            'status' => 'candidate',
            'packageMetadata' => $this->metadata(),
            'details' => [
                'files' => [],
                'directories' => ['/tests/'],
                'suggestions' => ["/tests\texport-ignore"],
                'totalSizeBytes' => $size,
                'humanReadableSize' => $size . ' B',
                'pathSizes' => ['/tests/' => $size],
            ],
        ];
    }

    /** @return array{name: string, version: string, sourceUrl: string, sourceReference: string, distUrl: null, distReference: null} */
    private function metadata(): array
    {
        return [
            'name' => 'vendor/package',
            'version' => '1.0.0',
            'sourceUrl' => 'https://github.com/vendor/package.git',
            'sourceReference' => 'abc123',
            'distUrl' => null,
            'distReference' => null,
        ];
    }
}
