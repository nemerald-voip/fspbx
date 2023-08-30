<?php

namespace App\Http\Livewire\CallDetailRecords;

use Carbon\Carbon;
use App\Models\CDR;
use Livewire\Component;
use App\Models\Extensions;
use Carbon\CarbonInterval;
use App\Models\CallCenterQueues;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Filters\DateFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\MultiSelectFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\MultiSelectDropdownFilter;

class CdrTable extends DataTableComponent
{
    // protected $model = CDR::class;
    public $period;

    public function configure(): void
    {
        $this->setPrimaryKey('xml_cdr_uuid');
        $this->setAdditionalSelects(['xml_cdr_uuid']);
        $this->setEmptyMessage('No results found');
        $this->setFilterLayoutSlideDown();
        $this->setDefaultSort('start_epoch', 'desc');
        $this->setSearchDebounce(1000);

        $this->setConfigurableAreas([
            'after-toolbar' => [
                'layouts.cdrs.call-category-filter'
            ],
        ]);
    }

    public function builder(): Builder
    {
        return CDR::query()
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->where('direction', '<>', 'outbound')
            ->select(
                'xml_cdr_uuid',
                'domain_uuid',
                'direction',
                'caller_id_name',
                'caller_id_number',
                'caller_destination',
                'source_number',
                'destination_number',
                'start_epoch',
                'start_stamp',
                'answer_stamp',
                'answer_epoch',
                'end_epoch',
                'end_stamp',
                'duration',
                'record_path',
                'record_name',
                'leg',
                'voicemail_message',
                'missed_call',
                'call_center_queue_uuid',
                'cc_side',
                'cc_queue_joined_epoch',
                'cc_queue',
                'cc_agent',
                'cc_queue_answered_epoch',
                'cc_queue_terminated_epoch',
                'waitsec',
                'hangup_cause',

            ); 
    }

    public function columns(): array
    {
        return [
            Column::make('Direction', 'direction')
                ->sortable(),
            Column::make('Caller Name', 'caller_id_name')
                ->sortable()
                ->searchable(
                    fn(Builder $query, $searchTerm) => $query->where('caller_id_name', 'iLIKE', '%'.$searchTerm.'%')
                ),
            Column::make('Caller ID', 'caller_id_number')
                ->sortable()
                ->searchable()
                ->format(
                    function ($value, $row, Column $column) {
                        return formatPhoneNumber($value);
                    }
                ),
            Column::make('Destination', 'caller_destination')
                ->sortable()
                ->searchable()
                ->format(
                    function ($value, $row, Column $column) {
                        return formatPhoneNumber($value);
                    }
                ),
            Column::make('Destination Number', 'destination_number')
                ->sortable()
                ->searchable()
                ->format(
                    function ($value, $row, Column $column) {
                        return formatPhoneNumber($value);
                    }
                ),
            Column::make('Date', 'start_epoch')
                ->sortable()
                ->format(
                    function ($value, $row, Column $column) {
                        // Convert epoch timestamp to Los Angeles time zone
                        $utcDateTime = Carbon::createFromTimestamp($value, 'UTC');
                        $localDateTime = $utcDateTime->setTimezone(get_local_time_zone(Session::get('domain_uuid')));

                        return $localDateTime->format('M d, Y');
                    }
                ),
            Column::make('Time', 'start_epoch')
                ->sortable()
                ->format(
                    function ($value, $row, Column $column) {
                        // Convert epoch timestamp to Los Angeles time zone
                        $utcDateTime = Carbon::createFromTimestamp($value, 'UTC');
                        $localDateTime = $utcDateTime->setTimezone(get_local_time_zone(Session::get('domain_uuid')));

                        return $localDateTime->format('g:i:s A');
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

        $filters = [
            DateFilter::make('Date From')
                ->filter(function (Builder $builder, string $value) use ($timezone) {
                    $startLocal = Carbon::createFromFormat('Y-m-d', $value, $timezone)->startOfDay();
                    $startUTC = $startLocal->setTimezone('UTC');
                    $builder->where('start_stamp', '>=', $startUTC);
                })
                ->hiddenFromMenus(),
            DateFilter::make('Date To')
                ->filter(function (Builder $builder, string $value) use ($timezone) {
                    $startLocal = Carbon::createFromFormat('Y-m-d', $value, $timezone)->endOfDay();
                    $startUTC = $startLocal->setTimezone('UTC');
                    $builder->where('start_stamp', '<=', $startUTC);
                })
                ->hiddenFromMenus(),

            MultiSelectFilter::make('Call Category')
                ->options([
                    1 => 'Answered',
                    2 => 'Missed',
                    3 => 'Abandoned',
                    4 => 'Agent Missed',
                ])
                ->filter(function (Builder $builder, array $values) {
                    logger($values);

                    $builder->where(function ($query) use ($builder, $values) {
                        foreach ($values as $value) {
                            if ($value === '2') {
                                    $builder
                                        ->orWhere('missed_call', true);
                                // } elseif ($value === '3') {
                                //     $builder->where('cc_side', 'agent')
                                //         ->where('missed_call', true);
                                // }
                            }
                        }

                    });
                    // if ($value === '1') {
                    //     $builder
                    //         ->where('missed_call', true);
                    // } elseif ($value === '2') {
                    //     $builder->where('cc_side', 'agent')
                    //         ->where('missed_call', true);
                    // }
                }),

            MultiSelectDropdownFilter::make('Users and Groups')
                ->options(
                    Extensions::query()
                        ->where('domain_uuid', Session::get('domain_uuid'))
                        ->orderBy('effective_caller_id_name')
                        ->get()
                        ->keyBy('extension_uuid')
                        ->map(fn ($extension) => $extension->effective_caller_id_name)
                        ->toArray()
                ),
        ];

        // Conditionally add Contact Center filter based on a certain condition

        // Fetch the call center queues
        $callCenterQueues = CallCenterQueues::query()
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->orderBy('queue_name')
            ->get()
            ->keyBy('call_center_queue_uuid')
            ->map(fn ($queue) => $queue->queue_name)
            ->toArray();

        if (!empty($callCenterQueues)) {
            $filters[] = MultiSelectDropdownFilter::make('Contact Centers')
                ->options($callCenterQueues)
                ->filter(function (Builder $builder, array $values) {
                    $builder->wherein('call_center_queue_uuid', $values);
                });
        }


        return $filters;
    }

    // public function mount()
    // {

    // }

    public function setDateRange($dateFrom,$dateTo)
    {
        $this->setFilter('date_from', $dateFrom);
        $this->setFilter('date_to', $dateTo);
    }
}
