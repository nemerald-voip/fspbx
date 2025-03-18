<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithProperties;

class CdrsExport implements FromView, WithProperties
{
    protected $cdrs;

    public function __construct($cdrs)
    {
        $this->cdrs = $cdrs;
    }

    public function view(): View
    {
        return view('layouts.cdrs.export', [
            'cdrs' => $this->cdrs
        ]);
    }

    // /**
    // * @return \Illuminate\Support\Collection
    // */
    // public function collection()
    // {
    //     return $this->cdrs;
    // }

    public function properties(): array
    {
        return [
            'creator'        => config('app.name', 'Laravel'),
            'lastModifiedBy' => config('app.name', 'Laravel'),
            'title'          => 'Call Record Export',
            'description'    => 'Exported call records',
            'subject'        => 'Call Records',
            'keywords'       => 'calls,export,spreadsheet',
            'category'       => 'Call Records',
        ];
    }
}
