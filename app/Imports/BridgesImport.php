<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

/**
 * Reads the uploaded CSV into memory. Validation and saving happen in BridgeService.
 *
 * Every cell is read as text: the default binder turns "false" into a boolean and
 * drops leading zeros from numbers.
 */
class BridgesImport extends StringValueBinder implements ToCollection, WithHeadingRow, SkipsEmptyRows, WithCustomValueBinder
{
    use Importable;

    public Collection $rows;

    public function __construct()
    {
        $this->rows = collect();
    }

    public function collection(Collection $rows): void
    {
        $this->rows = $rows;
    }
}
