<?php

declare(strict_types=1);

namespace App\Services\Spreadsheet;

use App\Models\SpreadsheetDataset;

/**
 * Leitura da planilha de performance — implementação concreta virá no passo de parse (xlsx).
 */
interface SpreadsheetReaderInterface
{
    public function loadFromPath(string $absolutePath): SpreadsheetDataset;
}
