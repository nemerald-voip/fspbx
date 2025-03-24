<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ContactTemplate implements FromCollection, WithHeadings
{
    /**
     * Define the headings (columns) for your CSV
     */
    public function headings(): array
    {
        return [
            'contact_name',
            'destination_number',
            'speed_dial_code',
            'assigned_user'
        ];
    }

    /**
     * Return a collection with sample data
     */
    public function collection()
    {
        // Provide a sample row (assigned_user can be empty)
        return new Collection([
            [
                'Example Contact',
                '3105552020',
                '10',
                '' 
            ]
        ]);
    }
}
