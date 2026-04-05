<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\SpreadsheetDataset;

/**
 * Monta payload do dashboard com ECharts e filtros em tempo real
 */
final class DashboardDataService
{
    public function build(SpreadsheetDataset $dataset): DashboardPayload
    {
        $data = DashboardDataProcessor::processDataset($dataset);
        $dashboard = $data['dashboard'];
        $filters = $data['filters'];

        return new DashboardPayload(
            kpis: $this->buildKPIs($dashboard),
            charts: $this->buildCharts($dashboard),
            filters: $this->buildFilters($filters),
        );
    }

    private function buildKPIs(array $data): array
    {
        $kpis = $data['kpis'];

        return [
            [
                'title' => 'Total de Participantes',
                'value' => number_format($kpis['total_participantes']),
                'icon' => 'fas fa-users',
                'color' => 'primary',
                'change' => '',
                'changeType' => 'neutral'
            ],
            [
                'title' => 'Taxa de Elegibilidade',
                'value' => number_format($kpis['taxa_elegibilidade'], 1) . '%',
                'icon' => 'fas fa-trophy',
                'color' => 'success',
                'change' => '',
                'changeType' => 'neutral'
            ],
            [
                'title' => 'Payout Estimado',
                'value' => 'R$ ' . number_format($kpis['payout_estimado'], 2, ',', '.'),
                'icon' => 'fas fa-dollar-sign',
                'color' => 'warning',
                'change' => '',
                'changeType' => 'neutral'
            ],
            [
                'title' => 'Atingimento Médio',
                'value' => number_format($kpis['atingimento_medio'], 1) . '%',
                'icon' => 'fas fa-chart-line',
                'color' => 'info',
                'change' => '',
                'changeType' => 'neutral'
            ],
        ];
    }

    private function buildCharts(array $data): array
    {
        return [
            'distributor_ranking' => $this->buildDistributorRankingChart($data),
            'category_performance' => $this->buildCategoryPerformanceChart($data),
            'eligibility_distribution' => $this->buildEligibilityDistributionChart($data),
            'volume_vs_positivacao' => $this->buildVolumeVsPositivacaoChart($data),
        ];
    }

    private function buildDistributorRankingChart(array $data): array
    {
        $distributors = $data['distributors'];

        // Ordenar por volume percentual (decrescente)
        usort($distributors, fn($a, $b) => $b['volume_percentual'] <=> $a['volume_percentual']);

        $top10 = array_slice($distributors, 0, 10);

        return [
            'xAxis' => [
                'type' => 'category',
                'data' => array_map(fn($d) => substr($d['nome'], 0, 15) . '...', $top10),
            ],
            'yAxis' => [
                'type' => 'value',
                'axisLabel' => ['formatter' => '{value}%']
            ],
            'series' => [
                [
                    'type' => 'bar',
                    'data' => array_map(fn($d) => round($d['volume_percentual'], 1), $top10),
                    'itemStyle' => [
                        'color' => function($params) {
                            return $params['value'] >= 100 ? '#28a745' : '#ffc107';
                        }
                    ]
                ]
            ],
            'tooltip' => ['trigger' => 'axis'],
            'grid' => ['left' => '3%', 'right' => '4%', 'bottom' => '3%', 'containLabel' => true],
        ];
    }

    private function buildCategoryPerformanceChart(array $data): array
    {
        $categories = $data['categories'];

        // Filtrar apenas categorias foco
        $focusCategories = array_filter($categories, fn($c) => $c['foco']);

        // Ordenar por percentual
        usort($focusCategories, fn($a, $b) => $b['percentual'] <=> $a['percentual']);

        return [
            'xAxis' => [
                'type' => 'category',
                'data' => array_map(fn($c) => substr($c['nome'], 0, 20), $focusCategories),
            ],
            'yAxis' => [
                'type' => 'value'
            ],
            'series' => [
                [
                    'name' => 'Meta',
                    'type' => 'bar',
                    'data' => array_map(fn($c) => round($c['total_meta'], 0), $focusCategories),
                    'itemStyle' => ['color' => '#6c757d']
                ],
                [
                    'name' => 'Realizado',
                    'type' => 'bar',
                    'data' => array_map(fn($c) => round($c['total_realizado'], 0), $focusCategories),
                    'itemStyle' => [
                        'color' => function($params) use ($focusCategories) {
                            $cat = $focusCategories[$params['dataIndex']];
                            return $cat['percentual'] >= 100 ? '#28a745' : '#dc3545';
                        }
                    ]
                ]
            ],
            'tooltip' => ['trigger' => 'axis'],
            'legend' => ['data' => ['Meta', 'Realizado']],
            'grid' => ['left' => '3%', 'right' => '4%', 'bottom' => '3%', 'containLabel' => true],
        ];
    }

    private function buildEligibilityDistributionChart(array $data): array
    {
        $distributors = $data['distributors'];

        $elegiveis = count(array_filter($distributors, fn($d) => $d['elegivel']));
        $inelegiveis = count($distributors) - $elegiveis;

        return [
            'series' => [
                [
                    'type' => 'pie',
                    'radius' => '60%',
                    'data' => [
                        [
                            'value' => $elegiveis,
                            'name' => 'Elegíveis',
                            'itemStyle' => ['color' => '#28a745']
                        ],
                        [
                            'value' => $inelegiveis,
                            'name' => 'Inelegíveis',
                            'itemStyle' => ['color' => '#dc3545']
                        ]
                    ],
                    'emphasis' => [
                        'itemStyle' => [
                            'shadowBlur' => 10,
                            'shadowOffsetX' => 0,
                            'shadowColor' => 'rgba(0, 0, 0, 0.5)'
                        ]
                    ],
                    'label' => [
                        'formatter' => '{b}: {c} ({d}%)'
                    ]
                ]
            ],
            'tooltip' => ['trigger' => 'item'],
        ];
    }

    private function buildVolumeVsPositivacaoChart(array $data): array
    {
        $distributors = $data['distributors'];

        // Scatter plot: Volume % vs Positivação %
        $scatterData = array_map(function($d) {
            return [
                round($d['volume_percentual'], 1),
                round($d['positivacao_percentual'], 1),
                $d['nome'],
                $d['elegivel']
            ];
        }, $distributors);

        return [
            'xAxis' => [
                'type' => 'value',
                'name' => 'Volume (%)',
                'nameLocation' => 'middle',
                'nameGap' => 30
            ],
            'yAxis' => [
                'type' => 'value',
                'name' => 'Positivação (%)',
                'nameLocation' => 'middle',
                'nameGap' => 40
            ],
            'series' => [
                [
                    'type' => 'scatter',
                    'data' => $scatterData,
                    'symbolSize' => function($data) {
                        return $data[3] ? 12 : 8; // Maior se elegível
                    },
                    'itemStyle' => [
                        'color' => function($params) {
                            $data = $params['data'];
                            return $data[3] ? '#28a745' : '#dc3545'; // Verde se elegível
                        }
                    ],
                    'tooltip' => [
                        'formatter' => function($params) {
                            $data = $params['data'];
                            return $data[2] + '<br/>Volume: ' + $data[0] + '%<br/>Positivação: ' + $data[1] + '%';
                        }
                    ]
                ]
            ],
            'visualMap' => [
                [
                    'type' => 'piecewise',
                    'pieces' => [
                        ['value' => 1, 'label' => 'Elegível', 'color' => '#28a745'],
                        ['value' => 0, 'label' => 'Inelegível', 'color' => '#dc3545']
                    ],
                    'dimension' => 3,
                    'orient' => 'horizontal',
                    'top' => 10,
                    'left' => 'center'
                ]
            ],
            'grid' => ['left' => '3%', 'right' => '4%', 'bottom' => '3%', 'containLabel' => true],
        ];
    }

    private function buildFilters(array $filters): array
    {
        return [
            'periodo' => array_map(fn($p) => ['value' => $p, 'label' => $p], $filters['periods']),
            'categoria' => array_map(fn($c) => ['value' => $c, 'label' => $c], $filters['categories']),
            'grupo' => array_map(fn($g) => ['value' => $g, 'label' => 'Grupo ' . $g], $filters['groups']),
            'status' => [
                ['value' => 'Elegível', 'label' => 'Elegível'],
                ['value' => 'Inelegível', 'label' => 'Inelegível']
            ],
        ];
    }
}
