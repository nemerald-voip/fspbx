<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DevicesTemplate implements FromCollection, WithHeadings
{
    public function headings(): array
    {
        return [
            'mac_address',
            'serial_number',
            'associated_extension',
        ];
    }

    public function collection()
    {
        return new Collection([
            [
                '00:25:9c:cf:1c:ac',
                'abc123456789',
                '1001',
            ],
        ]);
    }
}

