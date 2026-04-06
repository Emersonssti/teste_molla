<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Linha da aba Performance — espelha colunas da planilha (sem persistência).
 */
final class PerformanceRow
{
    private const MONTH_MAP = [
        'jan' => 1, 'janeiro' => 1,
        'fev' => 2, 'fevereiro' => 2,
        'mar' => 3, 'março' => 3, 'marco' => 3,
        'abr' => 4, 'abril' => 4,
        'mai' => 5, 'maio' => 5,
        'jun' => 6, 'junho' => 6,
        'jul' => 7, 'julho' => 7,
        'ago' => 8, 'agosto' => 8,
        'set' => 9, 'setembro' => 9,
        'out' => 10, 'outubro' => 10,
        'nov' => 11, 'novembro' => 11,
        'dez' => 12, 'dezembro' => 12,
    ];

    public function __construct(
        public readonly string $distributorId,
        public readonly string $distributorCnpj,
        public readonly string $distributorName,
        public readonly int $grupo,
        public readonly string $categoryName,
        public readonly string $kpiType,
        public readonly bool $isFocusCategory,
        public readonly string $referencePeriod,
        public readonly ?int $period,
        public readonly ?string $mes,
        public readonly ?int $mesNumero,
        public readonly ?int $ano,
        public readonly ?float $meta,
        public readonly ?float $realizado,
        public readonly ?float $cobertura,
    ) {
    }

    /**
     * @param array<string, mixed> $row Mapa cabeçalho => valor (chaves como na planilha).
     */
    public static function fromSpreadsheetRow(array $row): self
    {
        $mesLabel = self::monthLabel($row);
        return new self(
            distributorId: (string) ($row['distributors.id'] ?? ''),
            distributorCnpj: (string) ($row['distributors.cnpj'] ?? ''),
            distributorName: (string) ($row['distributors.name'] ?? ''),
            grupo: (int) ($row['Grupo'] ?? 0),
            categoryName: (string) ($row['categories.name'] ?? ''),
            kpiType: (string) ($row['kpiType'] ?? ''),
            isFocusCategory: self::toBool($row['isFocusCategory'] ?? false),
            referencePeriod: (string) ($row['referencePeriod'] ?? ''),
            period: isset($row['period']) ? (int) $row['period'] : null,
            mes: $mesLabel,
            mesNumero: self::monthToNumber($mesLabel),
            ano: isset($row['ano']) ? (int) $row['ano'] : null,
            meta: isset($row['meta']) ? (float) $row['meta'] : null,
            realizado: isset($row['realizado']) ? (float) $row['realizado'] : null,
            cobertura: isset($row['cobertura']) ? (float) $row['cobertura'] : null,
        );
    }

    private static function monthToNumber(?string $mes): ?int
    {
        if ($mes === null || $mes === '') {
            return null;
        }
        $num = (int) $mes;
        if ($num >= 1 && $num <= 12) {
            return $num;
        }
        return self::MONTH_MAP[mb_strtolower(trim($mes))] ?? null;
    }

    private static function monthLabel(array $row): ?string
    {
        $targets = ['mês', 'mes'];
        foreach ($row as $key => $value) {
            if (in_array(mb_strtolower($key), $targets, true) && $value !== null && $value !== '') {
                return (string) $value;
            }
        }
        return null;
    }

    private static function toBool(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if ($v === '1' || $v === 1) {
            return true;
        }
        if ($v === '0' || $v === 0) {
            return false;
        }
        return (bool) $v;
    }
}
