<?php

declare(strict_types=1);

namespace App\Services\Settlement;

final class MonthCodes
{
    /** @var array<int, string> mês (1–12) => abreviação como na base (ex.: mai, set) */
    public const BY_NUMBER = [
        1 => 'jan',
        2 => 'fev',
        3 => 'mar',
        4 => 'abr',
        5 => 'mai',
        6 => 'jun',
        7 => 'jul',
        8 => 'ago',
        9 => 'set',
        10 => 'out',
        11 => 'nov',
        12 => 'dez',
    ];

    /** @var array<string, string> abreviação => nome para título */
    public const LABEL_PT = [
        'jan' => 'Janeiro',
        'fev' => 'Fevereiro',
        'mar' => 'Março',
        'abr' => 'Abril',
        'mai' => 'Maio',
        'jun' => 'Junho',
        'jul' => 'Julho',
        'ago' => 'Agosto',
        'set' => 'Setembro',
        'out' => 'Outubro',
        'nov' => 'Novembro',
        'dez' => 'Dezembro',
    ];

    public static function abbrFromNumber(int $month): string
    {
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException('Mês inválido: ' . $month);
        }
        return self::BY_NUMBER[$month];
    }

    public static function labelFromAbbr(string $abbr): string
    {
        $k = strtolower(trim($abbr));
        return self::LABEL_PT[$k] ?? ucfirst($k);
    }
}
