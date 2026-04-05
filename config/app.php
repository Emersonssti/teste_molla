<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);

return [
    'base_path' => $basePath,
    'debug' => true,
    'spreadsheet' => [
        'default_relative_path' => 'Base de Dados.xlsx',
        'performance_sheet' => 'Performance',
    ],
    'dashboard' => [
        'default_chart_months' => 6,
    ],
];
