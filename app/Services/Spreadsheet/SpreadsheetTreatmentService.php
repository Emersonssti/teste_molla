<?php

declare(strict_types=1);

namespace App\Services\Spreadsheet;

use App\Models\PerformanceRow;
use App\Services\Settlement\DistributorApuracao;
use App\Services\Settlement\SettlementReport;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class SpreadsheetTreatmentService
{
    private const BRAND       = 'FF671F';
    private const BRAND_DARK  = 'E0520A';
    private const WHITE       = 'FFFFFF';
    private const DARK        = '1F2937';
    private const GRAY_TEXT   = '6B7280';
    private const LIGHT_BG    = 'F8FAFC';
    private const BORDER      = 'E5E7EB';
    private const GREEN       = '059669';
    private const GREEN_BG    = 'ECFDF5';
    private const RED         = 'DC2626';
    private const RED_BG      = 'FEF2F2';
    private const YELLOW_BG   = 'FFFBEB';
    private const HEADER_BG   = '1F2937';

    public function buildTreatedWorkbook(SettlementReport $report): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
        $spreadsheet->removeSheetByIndex(0);

        $this->fillApuracaoSheet($spreadsheet, $report);
        $this->fillDetalheSheet($spreadsheet, $report);
        $this->fillRegrasSheet($spreadsheet, $report);

        $spreadsheet->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'dataflow_apur_');
        if ($path === false) {
            throw new \RuntimeException('Não foi possível criar arquivo temporário.');
        }
        $xlsxPath = $path . '.xlsx';
        if (!@rename($path, $xlsxPath)) {
            @unlink($path);
            throw new \RuntimeException('Falha ao preparar arquivo de saída.');
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();

        return $xlsxPath;
    }

    private function fillApuracaoSheet(Spreadsheet $ss, SettlementReport $report): void
    {
        $sheet = $ss->createSheet();
        $sheet->setTitle('Apuração Mensal');
        $sheet->getSheetView()->setZoomScale(110);

        $totalDist = count($report->distributors);
        $elegiveis = array_filter($report->distributors, fn(DistributorApuracao $d) => $d->elegivelPremioMensal);
        $totalEleg = count($elegiveis);
        $totalPayout = array_sum(array_map(fn(DistributorApuracao $d) => $d->totalPremio(), $report->distributors));
        $totalBase = array_sum(array_map(fn(DistributorApuracao $d) => $d->premioBateuLevou, $report->distributors));
        $totalAccel = array_sum(array_map(fn(DistributorApuracao $d) => $d->premioAcelerador, $report->distributors));
        $taxaEleg = $totalDist > 0 ? round(($totalEleg / $totalDist) * 100, 1) : 0;

        // ── Brand bar (row 1) ──
        $sheet->mergeCells('A1:O1');
        $sheet->getRowDimension(1)->setRowHeight(6);
        $sheet->getStyle('A1:O1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::BRAND);

        // ── Title (row 2) ──
        $sheet->mergeCells('A2:O2');
        $sheet->getRowDimension(2)->setRowHeight(36);
        $sheet->setCellValue('A2', '   APURAÇÃO MENSAL — PROGRAMA DE INCENTIVO');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => self::DARK]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // ── Subtitle (row 3) ──
        $sheet->mergeCells('A3:O3');
        $sheet->setCellValue('A3', '   Competência: ' . $report->periodTitle . '   |   Gerado em: ' . date('d/m/Y H:i'));
        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['size' => 10, 'color' => ['rgb' => self::GRAY_TEXT]],
        ]);

        // ── Summary cards (row 5-6) ──
        $summaryData = [
            ['B', 'DISTRIBUIDORES', (string)$totalDist],
            ['D', 'ELEGÍVEIS', $totalEleg . ' (' . $taxaEleg . '%)'],
            ['F', 'PRÊMIO BASE', 'R$ ' . number_format($totalBase, 2, ',', '.')],
            ['H', 'ACELERADOR', 'R$ ' . number_format($totalAccel, 2, ',', '.')],
            ['J', 'TOTAL PAYOUT', 'R$ ' . number_format($totalPayout, 2, ',', '.')],
        ];

        foreach ($summaryData as [$col, $label, $value]) {
            $nextCol = chr(ord($col) + 1);
            $sheet->mergeCells($col . '5:' . $nextCol . '5');
            $sheet->mergeCells($col . '6:' . $nextCol . '6');
            $sheet->setCellValue($col . '5', $label);
            $sheet->setCellValue($col . '6', $value);
            $sheet->getStyle($col . '5:' . $nextCol . '5')->applyFromArray([
                'font' => ['bold' => true, 'size' => 8, 'color' => ['rgb' => self::GRAY_TEXT]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->getStyle($col . '6:' . $nextCol . '6')->applyFromArray([
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => self::DARK]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->getStyle($col . '5:' . $nextCol . '6')->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::LIGHT_BG]],
                'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BORDER]]],
            ]);
        }

        // ── Data table ──
        $headerRow = 8;
        $headers = [
            'A' => ['label' => '#',                    'width' => 5],
            'B' => ['label' => 'Distribuidor',         'width' => 30],
            'C' => ['label' => 'CNPJ',                 'width' => 20],
            'D' => ['label' => 'Grupo',                'width' => 8],
            'E' => ['label' => 'Vol. Meta',            'width' => 14],
            'F' => ['label' => 'Vol. Realizado',       'width' => 14],
            'G' => ['label' => '% Volume',             'width' => 11],
            'H' => ['label' => 'Pos. Meta',            'width' => 12],
            'I' => ['label' => 'Pos. Realizada',       'width' => 14],
            'J' => ['label' => '% Positivação',        'width' => 13],
            'K' => ['label' => 'Cat. Foco ≥100%',      'width' => 14],
            'L' => ['label' => 'Status',               'width' => 14],
            'M' => ['label' => 'Prêmio Base (R$)',     'width' => 16],
            'N' => ['label' => 'Acelerador 20% (R$)',   'width' => 18],
            'O' => ['label' => 'Total a Pagar (R$)',   'width' => 17],
        ];

        foreach ($headers as $col => $info) {
            $sheet->setCellValue($col . $headerRow, $info['label']);
            $sheet->getColumnDimension($col)->setWidth($info['width']);
        }

        $sheet->getRowDimension($headerRow)->setRowHeight(28);
        $sheet->getStyle('A' . $headerRow . ':O' . $headerRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => self::WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_BG]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '374151']]],
        ]);
        $sheet->getStyle('B' . $headerRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $r = $headerRow + 1;
        $seq = 1;
        foreach ($report->distributors as $d) {
            $sheet->setCellValue('A' . $r, $seq);
            $sheet->setCellValue('B' . $r, $d->nome);
            $sheet->setCellValueExplicit('C' . $r, $d->cnpj, DataType::TYPE_STRING);
            $sheet->setCellValue('D' . $r, $d->grupo);
            $sheet->setCellValue('E' . $r, $d->volumeMeta);
            $sheet->setCellValue('F' . $r, $d->volumeRealizado);
            $sheet->setCellValue('G' . $r, $d->volumePercentual / 100);
            $sheet->setCellValue('H' . $r, $d->positivacaoMeta);
            $sheet->setCellValue('I' . $r, $d->positivacaoRealizada);
            $sheet->setCellValue('J' . $r, $d->positivacaoPercentual / 100);
            $sheet->setCellValue('K' . $r, $d->categoriasFocoCom100);
            $sheet->setCellValue('L' . $r, $d->elegivelPremioMensal ? '✓ Elegível' : '✗ Não Elegível');
            $sheet->setCellValue('M' . $r, $d->premioBateuLevou);
            $sheet->setCellValue('N' . $r, $d->premioAcelerador);
            $sheet->setCellValue('O' . $r, $d->totalPremio());

            $sheet->getStyle('G' . $r)->getNumberFormat()->setFormatCode('0.0%');
            $sheet->getStyle('J' . $r)->getNumberFormat()->setFormatCode('0.0%');
            $sheet->getStyle('E' . $r . ':F' . $r)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('H' . $r . ':I' . $r)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('M' . $r . ':O' . $r)->getNumberFormat()->setFormatCode('R$ #,##0.00');

            if ($d->elegivelPremioMensal) {
                $sheet->getStyle('A' . $r . ':O' . $r)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::GREEN_BG);
                $sheet->getStyle('L' . $r)->getFont()->setColor(new Color(self::GREEN))->setBold(true);
            } else {
                if ($seq % 2 === 0) {
                    $sheet->getStyle('A' . $r . ':O' . $r)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::LIGHT_BG);
                }
                $sheet->getStyle('L' . $r)->getFont()->setColor(new Color(self::RED));
            }

            $volOk = $d->volumePercentual >= 100;
            $sheet->getStyle('G' . $r)->getFont()->setColor(new Color($volOk ? self::GREEN : self::RED))->setBold(true);
            $posOk = $d->positivacaoPercentual >= 100;
            $sheet->getStyle('J' . $r)->getFont()->setColor(new Color($posOk ? self::GREEN : self::RED))->setBold(true);

            $r++;
            $seq++;
        }

        $firstDataRow = $headerRow + 1;
        $lastDataRow = $r - 1;

        if ($lastDataRow < $firstDataRow) {
            $sheet->setCellValue('A' . $firstDataRow, 'Nenhum distribuidor com movimento mensal nesta competência.');
            $sheet->mergeCells('A' . $firstDataRow . ':O' . $firstDataRow);
            $lastDataRow = $firstDataRow;
        }

        // Data borders
        $sheet->getStyle('A' . $firstDataRow . ':O' . $lastDataRow)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BORDER]]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A' . $firstDataRow . ':A' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D' . $firstDataRow . ':D' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('K' . $firstDataRow . ':L' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ── Totals row ──
        $totRow = $lastDataRow + 1;
        $sheet->setCellValue('B' . $totRow, 'TOTAIS');
        $sheet->setCellValue('M' . $totRow, $totalBase);
        $sheet->setCellValue('N' . $totRow, $totalAccel);
        $sheet->setCellValue('O' . $totRow, $totalPayout);
        $sheet->getStyle('M' . $totRow . ':O' . $totRow)->getNumberFormat()->setFormatCode('R$ #,##0.00');
        $sheet->getStyle('A' . $totRow . ':O' . $totRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::DARK]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::YELLOW_BG]],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::DARK]],
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::DARK]],
            ],
        ]);

        // ── Brand bar (bottom) ──
        $botRow = $totRow + 2;
        $sheet->mergeCells('A' . $botRow . ':O' . $botRow);
        $sheet->getRowDimension($botRow)->setRowHeight(4);
        $sheet->getStyle('A' . $botRow . ':O' . $botRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::BRAND);

        $sheet->freezePane('A' . ($headerRow + 1));
        $sheet->setAutoFilter('A' . $headerRow . ':O' . $lastDataRow);

        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
    }

    private function fillDetalheSheet(Spreadsheet $ss, SettlementReport $report): void
    {
        $sheet = $ss->createSheet();
        $sheet->setTitle('Movimento Detalhado');
        $sheet->getSheetView()->setZoomScale(110);

        // ── Brand bar ──
        $sheet->mergeCells('A1:L1');
        $sheet->getRowDimension(1)->setRowHeight(6);
        $sheet->getStyle('A1:L1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::BRAND);

        // ── Title ──
        $sheet->mergeCells('A2:L2');
        $sheet->getRowDimension(2)->setRowHeight(30);
        $sheet->setCellValue('A2', '   MOVIMENTO DETALHADO — REFERÊNCIA MENSAL');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::DARK]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->mergeCells('A3:L3');
        $sheet->setCellValue('A3', '   Competência: ' . $report->periodTitle);
        $sheet->getStyle('A3')->getFont()->setSize(10)->getColor()->setRGB(self::GRAY_TEXT);

        $headerRow = 5;
        $headers = [
            'A' => ['label' => 'Distribuidor',      'width' => 28],
            'B' => ['label' => 'CNPJ',              'width' => 20],
            'C' => ['label' => 'Grupo',             'width' => 8],
            'D' => ['label' => 'Categoria',         'width' => 22],
            'E' => ['label' => 'Indicador (KPI)',   'width' => 28],
            'F' => ['label' => 'Foco?',             'width' => 8],
            'G' => ['label' => 'Referência',        'width' => 12],
            'H' => ['label' => 'Mês',               'width' => 8],
            'I' => ['label' => 'Ano',               'width' => 8],
            'J' => ['label' => 'Meta',              'width' => 14],
            'K' => ['label' => 'Realizado',         'width' => 14],
            'L' => ['label' => 'Atingimento',       'width' => 12],
        ];

        foreach ($headers as $col => $info) {
            $sheet->setCellValue($col . $headerRow, $info['label']);
            $sheet->getColumnDimension($col)->setWidth($info['width']);
        }

        $sheet->getRowDimension($headerRow)->setRowHeight(26);
        $sheet->getStyle('A' . $headerRow . ':L' . $headerRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => self::WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_BG]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '374151']]],
        ]);
        $sheet->getStyle('A' . $headerRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('D' . $headerRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('E' . $headerRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $r = $headerRow + 1;
        foreach ($report->detailRows as $line) {
            $sheet->setCellValue('A' . $r, $line->distributorName);
            $sheet->setCellValueExplicit('B' . $r, $line->distributorCnpj, DataType::TYPE_STRING);
            $sheet->setCellValue('C' . $r, $line->grupo);
            $sheet->setCellValue('D' . $r, $line->categoryName);
            $sheet->setCellValue('E' . $r, $this->kpiLabel($line->kpiType));
            $sheet->setCellValue('F' . $r, $line->isFocusCategory ? 'Sim' : 'Não');
            $sheet->setCellValue('G' . $r, $this->periodLabel($line->referencePeriod));
            $sheet->setCellValue('H' . $r, (string)($line->mes ?? ''));
            $sheet->setCellValue('I' . $r, $line->ano);
            $sheet->setCellValue('J' . $r, $line->meta);
            $sheet->setCellValue('K' . $r, $line->realizado);

            $ating = $this->atingimentoCell($line);
            if ($ating !== '' && $ating !== null) {
                $sheet->setCellValue('L' . $r, (float)$ating);
                $sheet->getStyle('L' . $r)->getNumberFormat()->setFormatCode('0.0%');
                $pct = (float)$ating;
                if ($pct >= 1.0) {
                    $sheet->getStyle('L' . $r)->getFont()->setColor(new Color(self::GREEN))->setBold(true);
                } elseif ($pct >= 0.95) {
                    $sheet->getStyle('L' . $r)->getFont()->getColor()->setRGB('B45309');
                } else {
                    $sheet->getStyle('L' . $r)->getFont()->setColor(new Color(self::RED));
                }
            }

            $sheet->getStyle('J' . $r . ':K' . $r)->getNumberFormat()->setFormatCode('#,##0.00');

            if ($line->isFocusCategory) {
                $sheet->getStyle('F' . $r)->getFont()->setColor(new Color(self::GREEN))->setBold(true);
            }

            if (($r - $headerRow) % 2 === 0) {
                $sheet->getStyle('A' . $r . ':L' . $r)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::LIGHT_BG);
            }

            $r++;
        }

        $firstDataRow = $headerRow + 1;
        $lastDataRow = $r - 1;

        if ($lastDataRow < $firstDataRow) {
            $sheet->setCellValue('A' . $firstDataRow, 'Sem linhas de movimento para esta competência.');
            $sheet->mergeCells('A' . $firstDataRow . ':L' . $firstDataRow);
            $lastDataRow = $firstDataRow;
        }

        $sheet->getStyle('A' . $firstDataRow . ':L' . $lastDataRow)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BORDER]]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('C' . $firstDataRow . ':C' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F' . $firstDataRow . ':I' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('L' . $firstDataRow . ':L' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->freezePane('A' . ($headerRow + 1));
        $sheet->setAutoFilter('A' . $headerRow . ':L' . $lastDataRow);

        $botRow = $lastDataRow + 2;
        $sheet->mergeCells('A' . $botRow . ':L' . $botRow);
        $sheet->getRowDimension($botRow)->setRowHeight(4);
        $sheet->getStyle('A' . $botRow . ':L' . $botRow)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::BRAND);
    }

    private function fillRegrasSheet(Spreadsheet $ss, SettlementReport $report): void
    {
        $sheet = $ss->createSheet();
        $sheet->setTitle('Regras do Programa');
        $sheet->getSheetView()->setZoomScale(115);
        $sheet->getColumnDimension('A')->setWidth(3);
        $sheet->getColumnDimension('B')->setWidth(50);
        $sheet->getColumnDimension('C')->setWidth(40);

        // ── Brand bar ──
        $sheet->mergeCells('A1:C1');
        $sheet->getRowDimension(1)->setRowHeight(6);
        $sheet->getStyle('A1:C1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::BRAND);

        $sheet->mergeCells('A2:C2');
        $sheet->getRowDimension(2)->setRowHeight(36);
        $sheet->setCellValue('A2', '   REGRAS DO PROGRAMA DE INCENTIVO');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => self::DARK]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->mergeCells('A3:C3');
        $sheet->setCellValue('A3', '   Referência rápida para validação — ' . $report->periodTitle);
        $sheet->getStyle('A3')->getFont()->setSize(10)->getColor()->setRGB(self::GRAY_TEXT);

        $row = 5;
        $rules = [
            ['Período do Programa', '01/09/2024 a 31/08/2025'],
            ['Apuração', 'Mensal — elegibilidade verificada todo mês'],
            ['', ''],
            ['CRITÉRIOS DE ELEGIBILIDADE', ''],
            ['Volume Total das Categorias', 'Somatório de todas as categorias sell-out deve ser ≥ 100% da meta'],
            ['Positivação Foco (Total)', 'Somatório da positivação das categorias foco deve ser ≥ 100% da meta'],
            ['Positivação Foco (por Categoria)', 'Ao menos 2 categorias foco com positivação individual ≥ 100%'],
            ['Elegível = Bateu, Levou', 'Somente se atender os 3 critérios acima simultaneamente'],
            ['', ''],
            ['PREMIAÇÃO MENSAL', ''],
            ['Grupo 1', 'R$ 6.000,00'],
            ['Grupo 2', 'R$ 5.000,00'],
            ['Grupo 3', 'R$ 3.000,00'],
            ['Grupo 4', 'R$ 2.000,00'],
            ['Grupo 5', 'R$ 1.000,00'],
            ['', ''],
            ['ACELERADOR', ''],
            ['Mecânica', 'Uma categoria é escolhida como aceleradora a cada mês'],
            ['Bônus', '+20% sobre o prêmio base para quem atingir volume ≥ 100% na categoria aceleradora'],
            ['Pré-requisito', 'Somente para distribuidores que já são elegíveis (Bateu, Levou)'],
        ];

        foreach ($rules as [$labelText, $valueText]) {
            if ($labelText === '' && $valueText === '') {
                $row++;
                continue;
            }

            $isSection = $valueText === '' && $labelText !== '';

            if ($isSection) {
                $sheet->mergeCells('B' . $row . ':C' . $row);
                $sheet->setCellValue('B' . $row, $labelText);
                $sheet->getRowDimension($row)->setRowHeight(24);
                $sheet->getStyle('B' . $row . ':C' . $row)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => self::WHITE]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BRAND]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
            } else {
                $sheet->setCellValue('B' . $row, $labelText);
                $sheet->setCellValue('C' . $row, $valueText);
                $sheet->getStyle('B' . $row)->getFont()->setBold(true)->setSize(10)->getColor()->setRGB(self::DARK);
                $sheet->getStyle('C' . $row)->getFont()->setSize(10)->getColor()->setRGB(self::GRAY_TEXT);
                $sheet->getStyle('B' . $row . ':C' . $row)->applyFromArray([
                    'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BORDER]]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                ]);
                $sheet->getRowDimension($row)->setRowHeight(22);
            }
            $row++;
        }

        // ── Legend ──
        $row += 1;
        $sheet->mergeCells('B' . $row . ':C' . $row);
        $sheet->setCellValue('B' . $row, 'LEGENDA DA ABA "APURAÇÃO MENSAL"');
        $sheet->getStyle('B' . $row . ':C' . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => self::WHITE]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_BG]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(24);
        $row++;

        $legend = [
            ['✓ Elegível (linha verde)', 'Distribuidor atende todos os critérios e recebe premiação'],
            ['✗ Não Elegível', 'Não atende um ou mais critérios — sem premiação'],
            ['% Volume / % Positivação', 'Percentual de atingimento: verde ≥ 100%, vermelho < 100%'],
            ['Cat. Foco ≥100%', 'Quantidade de categorias foco que atingiram 100% de positivação'],
            ['Prêmio Base', 'Valor "Bateu, Levou" conforme grupo do distribuidor'],
            ['Acelerador 20%', 'Bônus por atingir volume na categoria aceleradora do mês'],
        ];

        foreach ($legend as [$sym, $desc]) {
            $sheet->setCellValue('B' . $row, $sym);
            $sheet->setCellValue('C' . $row, $desc);
            $sheet->getStyle('B' . $row)->getFont()->setBold(true)->setSize(10)->getColor()->setRGB(self::DARK);
            $sheet->getStyle('C' . $row)->getFont()->setSize(9)->getColor()->setRGB(self::GRAY_TEXT);
            $sheet->getStyle('B' . $row . ':C' . $row)->applyFromArray([
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BORDER]]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(22);
            $row++;
        }

        $row++;
        $sheet->mergeCells('A' . $row . ':C' . $row);
        $sheet->getRowDimension($row)->setRowHeight(4);
        $sheet->getStyle('A' . $row . ':C' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::BRAND);
    }

    private function kpiLabel(string $type): string
    {
        return match ($type) {
            'TOTAL_VOLUME' => 'Volume sell-out (por categoria)',
            'CATEGORY_VOLUME_FOCUS' => 'Volume — categoria foco',
            'CATEGORY_POSITIVATION_FOCUS' => 'Positivação — categoria foco',
            default => $type,
        };
    }

    private function periodLabel(string $ref): string
    {
        return match (strtoupper($ref)) {
            'MONTHLY' => 'Mensal',
            'QUARTERLY' => 'Trimestral',
            default => $ref,
        };
    }

    private function atingimentoCell(PerformanceRow $line): float|int|string
    {
        $m = $line->meta;
        if ($m !== null && abs($m) > 1e-9 && $line->realizado !== null) {
            return ($line->realizado / $m);
        }
        if ($line->cobertura !== null) {
            return $line->cobertura;
        }
        return '';
    }
}
