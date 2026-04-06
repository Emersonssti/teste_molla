<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\PerformanceRow;
use App\Models\SpreadsheetDataset;

/**
 * Cache e processamento de dados para dashboard
 */
final class DashboardDataProcessor
{
    private static ?array $cache = null;

    public static function processDataset(SpreadsheetDataset $dataset): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $rows = $dataset->all();
        $processed = self::processRows($rows);

        self::$cache = $processed;
        return $processed;
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }

    private static function processRows(array $rows): array
    {
        // Tabela 1: Todos os dados da planilha (raw data)
        $allData = [];

        // Tabelas de filtro (distinct)
        $periods = [];
        $categories = [];
        $distributors = [];
        $groups = [];

        foreach ($rows as $row) {
            // Adicionar à tabela de todos os dados
            $allData[] = [
                'distributorId' => $row->distributorId,
                'distributorCnpj' => $row->distributorCnpj,
                'distributorName' => $row->distributorName,
                'grupo' => $row->grupo,
                'categoryName' => $row->categoryName,
                'isFocusCategory' => $row->isFocusCategory,
                'kpiType' => $row->kpiType,
                'ano' => $row->ano,
                'mes' => $row->mesNumero,
                'meta' => $row->meta,
                'realizado' => $row->realizado,
            ];

            $periodKey = $row->ano . '-' . str_pad((string)($row->mesNumero ?? 0), 2, '0', STR_PAD_LEFT);
            if (!in_array($periodKey, $periods)) {
                $periods[] = $periodKey;
            }

            if (!in_array($row->categoryName, $categories)) {
                $categories[] = $row->categoryName;
            }

            $distKey = $row->distributorId . '|' . $row->distributorName;
            if (!in_array($distKey, $distributors)) {
                $distributors[$row->distributorId] = [
                    'id' => $row->distributorId,
                    'nome' => $row->distributorName,
                    'cnpj' => $row->distributorCnpj,
                ];
            }

            if (!in_array($row->grupo, $groups)) {
                $groups[] = $row->grupo;
            }
        }

        // Ordenar os filtros
        sort($periods);
        sort($categories);
        sort($groups);

        // Converter distributors para array indexado
        $distributors = array_values($distributors);

        // Processar dados agregados para dashboard
        $processedData = self::processAggregatedData($allData);

        return [
            'allData' => $allData,
            'filters' => [
                'periods' => $periods,
                'categories' => $categories,
                'distributors' => $distributors,
                'groups' => $groups,
            ],
            'dashboard' => $processedData,
        ];
    }

    private static function processAggregatedData(array $allData): array
    {
        $distributors = [];
        $categories = [];
        $periods = [];
        $monthlyPerformance = []; // Para histórico de performance

        foreach ($allData as $row) {
            // Agrupar por distribuidor
            $distId = $row['distributorId'];
            if (!isset($distributors[$distId])) {
                $distributors[$distId] = [
                    'id' => $distId,
                    'cnpj' => $row['distributorCnpj'],
                    'nome' => $row['distributorName'],
                    'grupo' => $row['grupo'],
                    'total_volume_meta' => 0.0,
                    'total_volume_realizado' => 0.0,
                    'positivacao_foco_meta' => 0.0,
                    'positivacao_foco_realizado' => 0.0,
                    'categorias_foco' => [],
                    'categorias_volume_foco' => [],
                    'categorias_positivacao_foco' => [],
                    'periodos' => [],
                    'monthly_data' => [], // Para histórico mensal
                ];
            }

            // Agrupar por categoria
            $catName = $row['categoryName'];
            if (!isset($categories[$catName])) {
                $categories[$catName] = [
                    'nome' => $catName,
                    'foco' => $row['isFocusCategory'],
                    'total_meta' => 0.0,
                    'total_realizado' => 0.0,
                    'distribuidores' => [],
                ];
            }

            // Períodos disponíveis
            $periodKey = $row['ano'] . '-' . str_pad((string)($row['mes'] ?? 0), 2, '0', STR_PAD_LEFT);
            if (!in_array($periodKey, $periods)) {
                $periods[] = $periodKey;
            }

            // Agregar dados por KPI
            switch ($row['kpiType']) {
                case 'TOTAL_VOLUME':
                    $distributors[$distId]['total_volume_meta'] += $row['meta'] ?? 0;
                    $distributors[$distId]['total_volume_realizado'] += $row['realizado'] ?? 0;
                    $categories[$catName]['total_meta'] += $row['meta'] ?? 0;
                    $categories[$catName]['total_realizado'] += $row['realizado'] ?? 0;

                    // Dados mensais para histórico
                    $monthKey = $row['ano'] . '-' . str_pad((string)($row['mes'] ?? 0), 2, '0', STR_PAD_LEFT);
                    if (!isset($monthlyPerformance[$monthKey])) {
                        $monthlyPerformance[$monthKey] = [
                            'periodo' => $monthKey,
                            'volume_meta' => 0,
                            'volume_realizado' => 0,
                        ];
                    }
                    $monthlyPerformance[$monthKey]['volume_meta'] += $row['meta'] ?? 0;
                    $monthlyPerformance[$monthKey]['volume_realizado'] += $row['realizado'] ?? 0;
                    break;

                case 'CATEGORY_POSITIVATION_FOCUS':
                    if ($row['isFocusCategory']) {
                        $distributors[$distId]['positivacao_foco_meta'] += $row['meta'] ?? 0;
                        $distributors[$distId]['positivacao_foco_realizado'] += $row['realizado'] ?? 0;
                        $distributors[$distId]['categorias_foco'][] = $catName;
                        $distributors[$distId]['categorias_positivacao_foco'][] = [
                            'categoria' => $catName,
                            'meta' => $row['meta'] ?? 0,
                            'realizado' => $row['realizado'] ?? 0,
                        ];
                    }
                    break;

                case 'CATEGORY_VOLUME_FOCUS':
                    if ($row['isFocusCategory']) {
                        $distributors[$distId]['categorias_volume_foco'][] = [
                            'categoria' => $catName,
                            'meta' => $row['meta'] ?? 0,
                            'realizado' => $row['realizado'] ?? 0,
                        ];
                    }
                    break;
            }

            // Tracking de períodos por distribuidor
            if (!in_array($periodKey, $distributors[$distId]['periodos'])) {
                $distributors[$distId]['periodos'][] = $periodKey;
            }

            // Distribuidores por categoria
            if (!in_array($distId, $categories[$catName]['distribuidores'])) {
                $categories[$catName]['distribuidores'][] = $distId;
            }
        }

        // Calcular KPIs finais e elegibilidade
        $totalPayout = 0;
        foreach ($distributors as &$dist) {
            $dist['categorias_foco'] = array_unique($dist['categorias_foco']);
            $dist['volume_percentual'] = $dist['total_volume_meta'] > 0
                ? ($dist['total_volume_realizado'] / $dist['total_volume_meta']) * 100
                : 0;

            $dist['positivacao_percentual'] = $dist['positivacao_foco_meta'] > 0
                ? ($dist['positivacao_foco_realizado'] / $dist['positivacao_foco_meta']) * 100
                : 0;

            // Contar categorias foco com positivação 100% (agrupando por categoria)
            $positivacaoPorCat = [];
            foreach ($dist['categorias_positivacao_foco'] as $catPos) {
                $cat = $catPos['categoria'];
                if (!isset($positivacaoPorCat[$cat])) {
                    $positivacaoPorCat[$cat] = ['meta' => 0.0, 'realizado' => 0.0];
                }
                $positivacaoPorCat[$cat]['meta'] += $catPos['meta'];
                $positivacaoPorCat[$cat]['realizado'] += $catPos['realizado'];
            }
            $categoriasPositivacaoFoco100 = 0;
            foreach ($positivacaoPorCat as $agg) {
                if ($agg['meta'] > 0 && ($agg['realizado'] / $agg['meta']) >= 1.0) {
                    $categoriasPositivacaoFoco100++;
                }
            }
            $dist['categorias_positivacao_foco_100'] = $categoriasPositivacaoFoco100;

            $dist['elegivel'] = ($dist['volume_percentual'] >= 100) &&
                               ($dist['positivacao_percentual'] >= 100) &&
                               ($categoriasPositivacaoFoco100 >= 2);

            // Calcular premiação se elegível
            $dist['premiacao'] = 0;
            if ($dist['elegivel']) {
                $dist['premiacao'] = self::calcularPremiacao($dist['grupo']);
                $totalPayout += $dist['premiacao'];
            }
        }

        // Calcular percentuais das categorias
        foreach ($categories as &$cat) {
            $cat['percentual'] = $cat['total_meta'] > 0
                ? ($cat['total_realizado'] / $cat['total_meta']) * 100
                : 0;
        }

        // Calcular médias para radar chart (categorias foco)
        $categoriasFoco = array_filter($categories, fn($c) => $c['foco']);
        $radarData = [];
        foreach ($categoriasFoco as $cat) {
            $radarData[] = [
                'categoria' => $cat['nome'],
                'atingimento' => $cat['percentual'],
            ];
        }

        // Processar dados mensais
        ksort($monthlyPerformance);
        $historicoPerformance = array_values($monthlyPerformance);
        foreach ($historicoPerformance as &$month) {
            $month['percentual'] = $month['volume_meta'] > 0
                ? ($month['volume_realizado'] / $month['volume_meta']) * 100
                : 0;
        }

        // KPIs gerais
        $totalDistribuidores = count($distributors);
        $distribuidoresElegiveis = count(array_filter($distributors, fn($d) => $d['elegivel']));
        $taxaElegibilidade = $totalDistribuidores > 0 ? ($distribuidoresElegiveis / $totalDistribuidores) * 100 : 0;

        $volumeTotalMeta = array_sum(array_column($distributors, 'total_volume_meta'));
        $volumeTotalRealizado = array_sum(array_column($distributors, 'total_volume_realizado'));
        $atingimentoMedio = $volumeTotalMeta > 0 ? ($volumeTotalRealizado / $volumeTotalMeta) * 100 : 0;

        $kpis = [
            'total_participantes' => $totalDistribuidores,
            'taxa_elegibilidade' => round($taxaElegibilidade, 2),
            'payout_estimado' => $totalPayout,
            'atingimento_medio' => round($atingimentoMedio, 2),
        ];

        sort($periods);

        return [
            'distributors' => array_values($distributors),
            'categories' => array_values($categories),
            'periods' => $periods,
            'kpis' => $kpis,
            'radar_data' => $radarData,
            'historico_performance' => $historicoPerformance,
        ];
    }

    private static function calcularPremiacao(int $grupo): float
    {
        $tabelaPremiacao = [
            1 => 6000.00,
            2 => 5000.00,
            3 => 3000.00,
            4 => 2000.00,
            5 => 1000.00,
        ];

        return $tabelaPremiacao[$grupo] ?? 0.00;
    }

    public static function getCacheStatus(): array
    {
        return [
            'has_cache' => self::$cache !== null,
            'cache_keys' => self::$cache ? array_keys(self::$cache) : [],
            'cache_size' => self::$cache ? count(self::$cache) : 0,
        ];
    }
}