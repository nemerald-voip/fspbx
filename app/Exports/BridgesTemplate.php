<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BridgesTemplate implements FromCollection, WithHeadings
{
    public function headings(): array
    {
        return [
            'bridge_name',
            'bridge_destination',
            'bridge_enabled',
            'bridge_description',
        ];
    }

    public function collection()
    {
        return new Collection([
            [
                'Example gateway',
                'sofia/gateway/00000000-0000-0000-0000-000000000000/15555550100',
                'true',
                'Replace the gateway UUID with one of your gateways',
            ],
        ]);
    }
}
