<?php

declare(strict_types=1);

namespace App\Services\Settlement;

use App\Models\PerformanceRow;

final class SettlementReport
{
    /**
     * @param list<DistributorApuracao> $distributors
     * @param list<PerformanceRow> $detailRows
     */
    public function __construct(
        public readonly string $periodTitle,
        public readonly string $monthAbbr,
        public readonly int $year,
        public readonly array $distributors,
        public readonly array $detailRows,
    ) {
    }
}
