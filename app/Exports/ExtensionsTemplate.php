<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExtensionsTemplate implements FromCollection, WithHeadings
{
    /**
     * Define the headings (columns) for your CSV
     */
    public function headings(): array
    {
        return [
            'extension',
            'first_name',
            'last_name',
            'outbound_caller_id_number',
            'description',
            'email',
            'device_address',      // as MAC xx:xx:xx:xx:xx:xx
            'device_vendor',
            'device_template',
        ];
    }

    /**
     * Return a collection with sample data
     */
    public function collection()
    {
        // Provide a sample row matching your import requirements
        return new Collection([
            [
                '1001',                 // extension (required, numeric)
                'Alice',                // first_name (required)
                'Johnson',              // last_name (optional)
                '+13105552020',           // outbound_caller_id_number (optional, US phone)
                'Sample user',          // description (optional)
                'alice@example.com',    // email (optional)
                '00:25:9c:cf:1c:ac',    // device_address (optional, MAC address)
                'Yealink',              // device_vendor (optional)
                'yealink/t53',                 // device_template (optional)
            ]
        ]);
    }
}
