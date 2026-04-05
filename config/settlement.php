<?php

declare(strict_types=1);

/**
 * Valores do regulamento (Bateu, Levou + 20% acelerador sobre o prêmio mensal).
 * Categoria aceleradora por competência: chave "YYYY-MM" (ex.: 2025-05) => nome exato da categoria na planilha.
 * Se não houver chave para o período, o acelerador fica R$ 0,00.
 */
return [
    'premio_bateu_levou' => [
        1 => 6000.0,
        2 => 5000.0,
        3 => 3000.0,
        4 => 2000.0,
        5 => 1000.0,
    ],
    'accelerator_category_by_period' => [
        // '2025-05' => 'CREME DE LEITE',
    ],
];
