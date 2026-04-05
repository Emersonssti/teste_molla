<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

/**
 * DTO serializável para views/API — KPIs e séries dos gráficos.
 *
 * @phpstan-type KpiItem array{title: string, value: string, trend: string, icon: string, color: string, up: bool}
 * @phpstan-type ChartSeries array{labels: list<string>, datasets: list<array<string, mixed>>}
 */
final class DashboardPayload
{
    /**
     * @param list<KpiItem> $kpis
     * @param array<string, ChartSeries|mixed> $charts
     */
    public function __construct(
        public readonly array $kpis = [],
        public readonly array $charts = [],
        public readonly array $filters = [],
    ) {
    }

    public function toJson(): string
    {
        return json_encode(
            ['kpis' => $this->kpis, 'charts' => $this->charts, 'filters' => $this->filters],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        );
    }
}
