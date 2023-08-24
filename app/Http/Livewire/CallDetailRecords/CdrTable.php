<?php

namespace App\Http\Livewire\CallDetailRecords;

use Carbon\Carbon;
use App\Models\CDR;
use Livewire\Component;
use App\Models\Extensions;
use Carbon\CarbonInterval;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\MultiSelectFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\MultiSelectDropdownFilter;

class CdrTable extends DataTableComponent
{
    protected $model = CDR::class;

    public function configure(): void
    {
        $this->setPrimaryKey('xml_cdr_uuid');
        $this->setAdditionalSelects(['xml_cdr_uuid']);
        $this->setEmptyMessage('No results found');
        $this->setFilterLayoutSlideDown();
        $this->setDefaultSort('start_epoch', 'desc');
    }

    public function columns(): array
    {
        return [
            Column::make('Direction', 'direction')
                ->sortable(),
            Column::make('Caller Name', 'caller_id_name')
                ->sortable(),
            Column::make('Caller ID', 'caller_id_number')
                ->sortable(),
            Column::make('Destination', 'caller_destination')
                ->sortable(),
            Column::make('Destination Number', 'destination_number')
                ->sortable(),
            Column::make('Date', 'start_epoch')
                ->sortable()
                ->format(
                    function ($value, $row, Column $column) {
                        // Convert epoch timestamp to Los Angeles time zone
                        $utcDateTime = Carbon::createFromTimestamp($value, 'UTC');
                        $losAngelesDateTime = $utcDateTime->setTimezone('America/Los_Angeles');

                        return $losAngelesDateTime->format('M d, Y');
                    }
                ),
            Column::make('Time', 'start_epoch')
                ->sortable()
                ->format(
                    function ($value, $row, Column $column) {
                        // Convert epoch timestamp to Los Angeles time zone
                        $utcDateTime = Carbon::createFromTimestamp($value, 'UTC');
                        $losAngelesDateTime = $utcDateTime->setTimezone('America/Los_Angeles');

                        return $losAngelesDateTime->format('g:i:s A');
                    }
                ),
            Column::make('Duration', 'duration')
                ->sortable()
                ->format(function ($value, $row, Column $column) {
                    // Convert duration in seconds to formatted string
                    $formattedDuration = '';

                    $minutes = floor($value / 60);
                    $seconds = $value % 60;

                    if ($minutes > 0) {
                        $formattedDuration .= $minutes . 'm ';
                    }

                    if ($seconds > 0 || empty($formattedDuration)) {
                        $formattedDuration .= $seconds . 's';
                    }

                    return $formattedDuration;
                }),
            Column::make('Hangup Cause', 'hangup_cause')
                ->sortable(),
        ];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('Call Category')
                ->options([
                    '' => 'All',
                    'Status' => [
                        1 => 'Missed',
                    ],
                ]),
            MultiSelectDropdownFilter::make('Users and Groups')
                ->options(
                    Extensions::query()
                        ->orderBy('effective_caller_id_name')
                        ->get()
                        ->keyBy('extension_uuid')
                        ->map(fn ($extension) => $extension->effective_caller_id_name)
                        ->toArray()
                ),
        ];
    }
}
