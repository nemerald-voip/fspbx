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
 * Reads the uploaded CSV into memory. Validation happens in AccessControlService.
 * Every cell is read as text so values are never converted to booleans or numbers.
 */
class AccessControlNodesImport extends StringValueBinder implements ToCollection, WithHeadingRow, SkipsEmptyRows, WithCustomValueBinder
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
