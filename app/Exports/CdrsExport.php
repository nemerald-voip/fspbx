<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;

class CdrsExport implements FromCollection
{
    protected $cdrs;

    public function __construct($cdrs)
    {
        $this->cdrs = $cdrs;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return $this->cdrs;
    }
}
