<?php

declare(strict_types=1);

namespace App\Services\Spreadsheet;

use App\Models\PerformanceRow;
use App\Models\SpreadsheetDataset;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Lê a aba Performance: exige exatamente 14 colunas no cabeçalho (linha 1).
 */
final class PerformanceSheetReader implements SpreadsheetReaderInterface
{
    private const EXPECTED_COLUMNS = 14;

    public function loadFromPath(string $absolutePath): SpreadsheetDataset
    {
        if (!is_readable($absolutePath)) {
            throw new \InvalidArgumentException('Arquivo não encontrado ou ilegível: ' . $absolutePath);
        }

        $spreadsheet = IOFactory::load($absolutePath);
        $sheetName = (string) (config('spreadsheet')['performance_sheet'] ?? 'Performance');
        $sheet = $spreadsheet->getSheetByName($sheetName);
        if ($sheet === null) {
            throw new InvalidSpreadsheetStructureException(
                'Aba "' . $sheetName . '" não encontrada na planilha.'
            );
        }

        $this->assertExactlyFourteenHeaderColumns($sheet);

        $headers = $this->readHeaders($sheet);
        $rows = [];
        $highestRow = (int) $sheet->getHighestDataRow();
        for ($r = 2; $r <= $highestRow; $r++) {
            $assoc = [];
            $empty = true;
            for ($c = 1; $c <= self::EXPECTED_COLUMNS; $c++) {
                $header = $headers[$c - 1];
                $coord = $this->coord($c, $r);
                $value = $sheet->getCell($coord)->getCalculatedValue();
                if ($value !== null && $value !== '') {
                    $empty = false;
                }
                $assoc[$header] = $value;
            }
            if ($empty) {
                continue;
            }
            $rows[] = PerformanceRow::fromSpreadsheetRow($assoc);
        }

        return SpreadsheetDataset::fromRows($rows, $absolutePath);
    }

    private function assertExactlyFourteenHeaderColumns(Worksheet $sheet): void
    {
        for ($c = 1; $c <= self::EXPECTED_COLUMNS; $c++) {
            $raw = $sheet->getCell($this->coord($c, 1))->getValue();
            if ($raw === null || trim((string) $raw) === '') {
                throw new InvalidSpreadsheetStructureException(
                    'A planilha deve ter exatamente 14 colunas de cabeçalho na linha 1. ' .
                    'Encontrada coluna vazia na posição ' . $c . '.'
                );
            }
        }

        $highestCol = $sheet->getHighestDataColumn(1);
        $idx = Coordinate::columnIndexFromString($highestCol);
        if ($idx !== self::EXPECTED_COLUMNS) {
            throw new InvalidSpreadsheetStructureException(
                'A quantidade de colunas no arquivo está incorreta: são necessárias 14 colunas ' .
                '(encontradas ' . $idx . ' com dados no cabeçalho).'
            );
        }
    }

    /**
     * @return list<string>
     */
    private function readHeaders(Worksheet $sheet): array
    {
        $headers = [];
        for ($c = 1; $c <= self::EXPECTED_COLUMNS; $c++) {
            $headers[] = trim((string) $sheet->getCell($this->coord($c, 1))->getValue());
        }
        return $headers;
    }

    private function coord(int $columnIndex1Based, int $row): string
    {
        return Coordinate::stringFromColumnIndex($columnIndex1Based) . $row;
    }
}
