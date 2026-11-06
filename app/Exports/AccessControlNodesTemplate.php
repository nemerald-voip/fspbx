<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AccessControlNodesTemplate implements FromCollection, WithHeadings
{
    public function headings(): array
    {
        return [
            'node_type',
            'node_cidr',
            'node_description',
        ];
    }

    public function collection()
    {
        return new Collection([
            ['allow', '203.0.113.10', 'Single address'],
            ['allow', '198.51.100.0/24', 'Whole range'],
            ['deny', '2001:db8::/32', 'IPv6 range'],
        ]);
    }
}
