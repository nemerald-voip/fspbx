<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use App\Services\CdrDataService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExtensionStatisticsExport implements FromCollection, WithHeadings
{
    protected array $params;
    protected CdrDataService $cdrDataService;

    public function __construct(array $params, CdrDataService $cdrDataService)
    {
        $this->params = $params;
        $this->cdrDataService = $cdrDataService;
    }

    public function collection(): Collection
    {
        $rows = $this->cdrDataService->getExtensionStatistics($this->params);

        // In case service returns paginator-like structure or plain array
        if (is_array($rows) && isset($rows['data'])) {
            $rows = collect($rows['data']);
        } elseif ($rows instanceof \Illuminate\Pagination\AbstractPaginator) {
            $rows = collect($rows->items());
        } else {
            $rows = collect($rows);
        }

        return $rows->map(function ($row) {
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