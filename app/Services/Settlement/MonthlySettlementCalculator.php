<?php

declare(strict_types=1);

namespace App\Services\Settlement;

use App\Models\PerformanceRow;
use App\Models\SpreadsheetDataset;

/**
 * Apuração mensal — regras do regulamento (volume total das categorias + positivação foco).
 */
final class MonthlySettlementCalculator
{
    private const EPS = 1e-6;

    /** @var array<int, float> */
    private array $premioBase;

    /** @var array<string, string> */
    private array $acceleratorCategoryByPeriod;

    public function __construct()
    {
        $cfg = require BASE_PATH . '/config/settlement.php';
        $this->premioBase = $cfg['premio_bateu_levou'];
        $this->acceleratorCategoryByPeriod = $cfg['accelerator_category_by_period'] ?? [];
    }

    public function calculate(SpreadsheetDataset $dataset, int $monthNumber, int $year): SettlementReport
    {
        $abbr = MonthCodes::abbrFromNumber($monthNumber);
        $long = MonthCodes::labelFromAbbr($abbr);
        $periodTitle = sprintf('%s/%d', $long, $year);

        $monthly = [];
        foreach ($dataset->all() as $row) {
            if (!$this->matchesMonthYear($row, $abbr, $year)) {
                continue;
            }
            if (strtoupper($row->referencePeriod) !== 'MONTHLY') {
                continue;
            }
            $monthly[] = $row;
        }

        $byDist = [];
        foreach ($monthly as $row) {
            $byDist[$row->distributorId][] = $row;
        }

        $lines = [];
        foreach ($byDist as $distId => $rows) {
            $lines[] = $this->apurarDistribuidor($distId, $rows, $monthNumber, $year);
        }

        usort($lines, static fn (DistributorApuracao $a, DistributorApuracao $b) => strcmp($a->nome, $b->nome));

        return new SettlementReport(
            periodTitle: $periodTitle,
            monthAbbr: $abbr,
            year: $year,
            distributors: $lines,
            detailRows: $monthly,
        );
    }

    private function apurarDistribuidor(string $distId, array $rows, int $monthNumber, int $year): DistributorApuracao
    {
        /** @var PerformanceRow $first */
        $first = $rows[0];
        $grupo = $first->grupo;

        $volumeRows = array_values(array_filter(
            $rows,
            static fn (PerformanceRow $r) => $r->kpiType === 'TOTAL_VOLUME'
        ));
        $sumMetaVol = 0.0;
        $sumRealVol = 0.0;
        foreach ($volumeRows as $r) {
            $sumMetaVol += (float) ($r->meta ?? 0);
            $sumRealVol += (float) ($r->realizado ?? 0);
        }
        $volumeOk = $sumMetaVol > self::EPS && ($sumRealVol + self::EPS) >= $sumMetaVol;

        $posRows = array_values(array_filter(
            $rows,
            static fn (PerformanceRow $r) => $r->kpiType === 'CATEGORY_POSITIVATION_FOCUS' && $r->isFocusCategory
        ));
        $sumMetaPos = 0.0;
        $sumRealPos = 0.0;
        $byCat = [];
        foreach ($posRows as $r) {
            $sumMetaPos += (float) ($r->meta ?? 0);
            $sumRealPos += (float) ($r->realizado ?? 0);
            $cat = $r->categoryName;
            if (!isset($byCat[$cat])) {
                $byCat[$cat] = ['meta' => 0.0, 'real' => 0.0];
            }
            $byCat[$cat]['meta'] += (float) ($r->meta ?? 0);
            $byCat[$cat]['real'] += (float) ($r->realizado ?? 0);
        }
        $posTotalOk = $sumMetaPos > self::EPS && ($sumRealPos + self::EPS) >= $sumMetaPos;

        $cats100 = 0;
        foreach ($byCat as $agg) {
            if ($agg['meta'] > self::EPS && ($agg['real'] + self::EPS) >= $agg['meta']) {
                $cats100++;
            }
        }

        $regraPosOk = $posTotalOk && $cats100 >= 2;
        $elegivel = $volumeOk && $regraPosOk;

        $base = isset($this->premioBase[$grupo]) ? (float) $this->premioBase[$grupo] : 0.0;
        $premioBateu = $elegivel ? $base : 0.0;

        $periodKey = sprintf('%04d-%02d', $year, $monthNumber);
        $accelCat = $this->acceleratorCategoryByPeriod[$periodKey] ?? null;
        $premioAccel = 0.0;
        if ($elegivel && $accelCat !== null && $accelCat !== '') {
            foreach ($volumeRows as $r) {
                if (strcasecmp(trim($r->categoryName), trim($accelCat)) !== 0) {
                    continue;
                }
                $m = (float) ($r->meta ?? 0);
                $re = (float) ($r->realizado ?? 0);
                if ($m > self::EPS && ($re + self::EPS) >= $m) {
                    $premioAccel = round($base * 0.20, 2);
                }
                break;
            }
        }

        $volPct = $sumMetaVol > self::EPS ? round(($sumRealVol / $sumMetaVol) * 100, 1) : 0.0;
        $posPct = $sumMetaPos > self::EPS ? round(($sumRealPos / $sumMetaPos) * 100, 1) : 0.0;

        $obs = $this->montarObservacoes($volumeOk, $posTotalOk, $cats100, $elegivel, $sumMetaVol, $sumMetaPos);

        return new DistributorApuracao(
            distributorId: $distId,
            nome: $first->distributorName,
            cnpj: $first->distributorCnpj,
            grupo: $grupo,
            volumeMeta: round($sumMetaVol, 2),
            volumeRealizado: round($sumRealVol, 2),
            volumePercentual: $volPct,
            volumeTotalCategorias100: $volumeOk,
            positivacaoMeta: round($sumMetaPos, 2),
            positivacaoRealizada: round($sumRealPos, 2),
            positivacaoPercentual: $posPct,
            positivacaoFocoTotal100: $posTotalOk,
            categoriasFocoCom100: $cats100,
            elegivelPremioMensal: $elegivel,
            premioBateuLevou: round($premioBateu, 2),
            premioAcelerador: $premioAccel,
            observacoes: $obs,
        );
    }

    private function montarObservacoes(
        bool $volumeOk,
        bool $posTotalOk,
        int $cats100,
        bool $elegivel,
        float $sumMetaVol,
        float $sumMetaPos,
    ): string {
        if ($elegivel) {
            return 'Atende volume total e positivação foco (100% no total e ≥2 categorias).';
        }
        $parts = [];
        if ($sumMetaVol <= self::EPS) {
            $parts[] = 'Sem metas de volume (TOTAL_VOLUME) para o período.';
        } elseif (!$volumeOk) {
            $parts[] = 'Volume total das categorias abaixo de 100%.';
        }
        if ($sumMetaPos <= self::EPS) {
            $parts[] = 'Sem metas de positivação foco no período.';
        } else {
            if (!$posTotalOk) {
                $parts[] = 'Positivação foco agregada abaixo de 100%.';
            }
            if ($cats100 < 2) {
                $parts[] = 'Menos de 2 categorias foco com 100% na positivação.';
            }
        }
        return implode(' ', $parts);
    }

    private function matchesMonthYear(PerformanceRow $r, string $monthAbbr, int $year): bool
    {
        if ($r->ano !== $year) {
            return false;
        }
        if ($r->mes === null || trim($r->mes) === '') {
            return false;
        }
        return strtolower(trim($r->mes)) === strtolower($monthAbbr);
    }
}
