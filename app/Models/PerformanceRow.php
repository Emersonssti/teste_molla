<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Linha da aba Performance — espelha colunas da planilha (sem persistência).
 */
final class PerformanceRow
{
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
            mes: self::monthLabel($row),
            ano: isset($row['ano']) ? (int) $row['ano'] : null,
            meta: isset($row['meta']) ? (float) $row['meta'] : null,
            realizado: isset($row['realizado']) ? (float) $row['realizado'] : null,
            cobertura: isset($row['cobertura']) ? (float) $row['cobertura'] : null,
        );
    }

    private static function monthLabel(array $row): ?string
    {
        foreach (['mês', 'mes'] as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return (string) $row[$key];
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
