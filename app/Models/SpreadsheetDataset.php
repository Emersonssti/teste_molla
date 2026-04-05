<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Conjunto de linhas lidas da planilha (memória apenas).
 *
 * @implements \IteratorAggregate<int, PerformanceRow>
 */
final class SpreadsheetDataset implements \Countable, \IteratorAggregate
{
    /** @param list<PerformanceRow> $rows */
    public function __construct(
        private array $rows = [],
        private readonly ?string $sourcePath = null,
    ) {
    }

    public static function empty(?string $sourcePath = null): self
    {
        return new self([], $sourcePath);
    }

    /**
     * @param list<PerformanceRow> $rows
     */
    public static function fromRows(array $rows, ?string $sourcePath = null): self
    {
        return new self($rows, $sourcePath);
    }

    public function count(): int
    {
        return count($this->rows);
    }

    /**
     * @return \ArrayIterator<int, PerformanceRow>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->rows);
    }

    /**
     * @return list<PerformanceRow>
     */
    public function all(): array
    {
        return $this->rows;
    }

    public function sourcePath(): ?string
    {
        return $this->sourcePath;
    }
}
