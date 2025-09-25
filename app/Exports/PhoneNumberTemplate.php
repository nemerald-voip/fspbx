<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PhoneNumberTemplate implements FromCollection, WithHeadings
{
    /**
     * Define the headings (columns) for your CSV
     */
    public function headings(): array
    {
        return [
            'country_code',
            'phone_number',
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
                '1',                 // country_code (required, numeric)
                '3105552020',                // phone_number (required)
            ]
        ]);
    }
}
