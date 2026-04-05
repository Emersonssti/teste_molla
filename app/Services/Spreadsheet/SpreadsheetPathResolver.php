<?php

declare(strict_types=1);

namespace App\Services\Spreadsheet;

/**
 * Resolve caminho absoluto do arquivo de dados na raiz do projeto.
 */
final class SpreadsheetPathResolver
{
    public function defaultPerformanceFile(): string
    {
        $cfg = config('spreadsheet');
        if (!is_array($cfg) || !isset($cfg['default_relative_path'])) {
            throw new \RuntimeException('Config spreadsheet.default_relative_path ausente.');
        }
        return $this->joinBase((string) $cfg['default_relative_path']);
    }

    public function joinBase(string $relative): string
    {
        $base = rtrim((string) config('base_path'), DIRECTORY_SEPARATOR);
        return $base . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative), DIRECTORY_SEPARATOR);
    }
}
