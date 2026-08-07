<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExtensionStatisticsExport implements FromCollection, WithHeadings
{
    public function __construct(
        protected Collection $rows
    ) {}

    public function collection(): Collection
    {
        return $this->rows->map(function ($row) {
            $row = (object) $row;

            return [
                'extension' => $row->extension_label ?? '',
                'total_calls' => $row->call_count ?? 0,
                'inbound' => $row->inbound ?? 0,
                'outbound' => $row->outbound ?? 0,
                'missed' => $row->missed ?? 0,
                'total_talk_time' => $row->total_talk_time_formatted ?? '',
                'average_call_duration' => $row->average_duration_formatted ?? '',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Extension',
            'Total Calls',
            'Inbound',
            'Outbound',
            'Missed',
            'Total Talk',
            'Avg Call Duration',
        ];
    }
}
