<?php

namespace App\Http\Livewire\CallDetailRecords;

use Carbon\Carbon;
use App\Models\CDR;
use Livewire\Component;
use App\Models\Extensions;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Filters\DateFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\MultiSelectFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\MultiSelectDropdownFilter;

class CdrTable extends DataTableComponent
{
    protected $model = CDR::class;
    public $period;

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
        $timezone = Cache::get(auth()->user()->user_uuid . '_timeZone');

        return [
            SelectFilter::make('Call Category')
                ->options([
                    '' => 'All',
                    'Status' => [
                        1 => 'Missed',
                    ],
                ])
                ->filter(function (Builder $builder, string $value) {
                    if ($value === '1') {
                        $builder->where('cc_side', 'agent')
                            ->where('missed_call', true);
                    }
                }),

            MultiSelectDropdownFilter::make('Users and Groups')
                ->options(
                    Extensions::query()
                        ->orderBy('effective_caller_id_name')
                        ->get()
                        ->keyBy('extension_uuid')
                        ->map(fn ($extension) => $extension->effective_caller_id_name)
                        ->toArray()
                ),
            DateFilter::make('Date From')
                ->filter(function (Builder $builder, string $value) use ($timezone) {
                    $startLocal = Carbon::createFromFormat('Y-m-d', $value, $timezone)->startOfDay();
                    $startUTC = $startLocal->setTimezone('UTC');
                    $builder->where('start_stamp', '>=', $startUTC);
                }),
            DateFilter::make('Date To')
                ->filter(function (Builder $builder, string $value) use ($timezone) {
                    $startLocal = Carbon::createFromFormat('Y-m-d', $value, $timezone)->endOfDay();
                    $startUTC = $startLocal->setTimezone('UTC');
                    $builder->where('start_stamp', '<=', $startUTC);
                }),
        ];
    }

    public function mount($period)
    {
        $this->period = periodHelper($period);
        $this->setFilter('date_from', $this->period[0]->format('Y-m-d'));
        $this->setFilter('date_to', $this->period[1]->format('Y-m-d'));

        $this->setFilter('call_category', '1');

        // logger($this->period);
    }
}
