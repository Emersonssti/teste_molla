<?php

declare(strict_types=1);

namespace App\Services\Settlement;

final class DistributorApuracao
{
    public function __construct(
        public readonly string $distributorId,
        public readonly string $nome,
        public readonly string $cnpj,
        public readonly int $grupo,
        public readonly float $volumeMeta,
        public readonly float $volumeRealizado,
        public readonly float $volumePercentual,
        public readonly bool $volumeTotalCategorias100,
        public readonly float $positivacaoMeta,
        public readonly float $positivacaoRealizada,
        public readonly float $positivacaoPercentual,
        public readonly bool $positivacaoFocoTotal100,
        public readonly int $categoriasFocoCom100,
        public readonly bool $elegivelPremioMensal,
        public readonly float $premioBateuLevou,
        public readonly float $premioAcelerador,
        public readonly string $observacoes,
    ) {
    }

    public function totalPremio(): float
    {
        return round($this->premioBateuLevou + $this->premioAcelerador, 2);
    }
}
