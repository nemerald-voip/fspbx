<?php

namespace App\Http\Livewire\CallDetailRecords;

use App\Http\Controllers\Cdrs;
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

    // To show/hide the modal
    public bool $modalIsOpen = false;

    // The information currently being displayed in the modal
    public $currentRecord;

    public function configure(): void
    {
        $this->setPrimaryKey('xml_cdr_uuid');
        // $this->setAdditionalSelects(['xml_cdr_uuid']);
        $this->setEmptyMessage('No results found');
        $this->setFilterLayoutSlideDown();
        $this->setDefaultSort('start_epoch', 'desc');
        $this->setSearchDebounce(1000);

        $this->setConfigurableAreas([
            'after-toolbar' => [
                'layouts.cdrs.filters'
            ],
        ]);

        // $this->setTrAttributes(function ($row, $index) {
        //     return [
        //         'default' => true,
        //         'wire:click.prevent' => "viewModal('" . $row->xml_cdr_uuid . "')",
        //     ];
        // });
    }

    // public function getTableRowUrl()
    // {
    //     return '#';
    // }


    public function viewModal($uuid): void
    {
        $this->modalIsOpen = true;
        $this->currentRecord = CDR::findOrFail($uuid);

        $this->dispatchBrowserEvent('open-module');
    }

    public function customView(): string
    {
        return 'layouts.cdrs.record-modal';
    }

    public function resetModal(): void
    {
        $this->reset('modalIsOpen', 'currentRecord');
    }

    public function builder(): Builder
    {
        $cdrs =  CDR::query()
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->where('direction', '<>', 'outbound')
            ->select(
                'xml_cdr_uuid',
                'domain_uuid',
                'sip_call_id',
                'source_number',
                'start_stamp',
                'answer_stamp',
                'answer_epoch',
                'end_epoch',
                'end_stamp',
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
                'cc_agent_bridged',
                'cc_queue_answered_epoch',
                'cc_queue_terminated_epoch',
                'cc_queue_canceled_epoch',
                'cc_cancel_reason',
                'cc_cause',
                'waitsec',
                'hangup_cause_q850',
                'sip_hangup_disposition'
            );

        //exclude legs that were not answered
        if (!userCheckPermission('xml_cdr_lose_race')) {
            $cdrs->where('hangup_cause', '!=', 'LOSE_RACE');
        }

        return $cdrs;
    }

    public function columns(): array
    {
        return [
            Column::make('Direction', 'direction')
                ->sortable()
                ->format(function ($value, $row, Column $column) {
                    if ($row->direction == "inbound") {
                        $icon = "mdi-arrow-bottom-left-thick";
                    } elseif ($row->direction == "outbound") {
                        $icon = 'mdi-arrow-top-right-thick';
                    } elseif ($row->direction == "local") {
                        $icon = 'mdi-arrow-left-right-bold';
                    }
                    return  view('layouts.cdrs.call-direction', ['icon' => $icon, 'direction' => $row->direction])->render();
                })
                ->html(),
            Column::make('Caller Name', 'caller_id_name')
                ->sortable()
                ->searchable(
                    fn (Builder $query, $searchTerm) => $query->where('caller_id_name', 'iLIKE', '%' . $searchTerm . '%')
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
                ->sortable()
                ->searchable()
                ->format(function ($value, $row, Column $column) {
                    $hint = null;
                    $color = 'primary';
                    if ($row->hangup_cause == "NORMAL_CLEARING" && $row->sip_hangup_disposition == 'recv_bye' && $row->voicemail_message == false) {
                        $value = $row->hangup_cause;
                        $hint = "The caller requested to end the call. The call was successfully answered and successfully ended.";
                    }

                    if ($row->hangup_cause == "NORMAL_CLEARING" && $row->sip_hangup_disposition == 'recv_bye' && $row->voicemail_message == true) {
                        $value = "Voicemail";
                        $hint = "The caller left a voicemail. The call was successfully ended.";
                    } elseif ($row->hangup_cause == "NORMAL_CLEARING" && $row->sip_hangup_disposition == 'send_bye') {
                        $value = $row->hangup_cause;
                        $hint = "Recipient requested to end the call. The call was successfully answered and successfully ended.";
                    } elseif ($row->hangup_cause == "ORIGINATOR_CANCEL") {
                        $value = $row->hangup_cause;
                        $hint = "The caller initiated a call and then hang up before the recipient picked up.";
                        $color = 'secondary';
                    } elseif ($row->hangup_cause == "NO_ANSWER" && $row->sip_hangup_disposition == 'send_cancel') {
                        $value = $row->hangup_cause;
                        $hint = "This cause is used when the called party has been alerted but does not respond with a connect indication within a prescribed period of time.";
                        $color = 'danger';
                    } elseif ($row->hangup_cause == "UNALLOCATED_NUMBER" && $row->sip_hangup_disposition == 'recv_refuse') {
                        $value = $row->hangup_cause;
                        $hint = "This cause indicates that the called party cannot be reached because, although the called party number is in a valid format, it is not currently allocated (assigned).";
                        $color = "dark";
                    } elseif ($row->cc_cancel_reason == "BREAK_OUT" && $row->cc_cause == 'cancel') {
                        $value =  "Abandoned";
                        $hint = "The call was initiated and ended before connecting with an agent";
                        $color = 'danger';
                    } elseif ($row->hangup_cause == "NORMAL_CLEARING" && $row->cc_cancel_reason == "EXIT_WITH_KEY" && $row->voicemail_message == true) {
                        $value = $row->cc_cancel_reason;
                        $hint = "The caller exited the queue by pressing an exit digit";
                    }
                    return  view('layouts.cdrs.hangup-cause', ['value' => $value, 'hint' => $hint, 'color' => $color])->render();
                })
                ->html(),

            Column::make('')
                ->label(function ($row, $column) {
                    return view('layouts.cdrs.actions', ['row' => $row]);
                }),
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
                    $builder->where(function ($query) use ($values) {
                        foreach ($values as $value) {
                            if ($value === '2') {
                                $query
                                    ->orWhere('missed_call', true);
                                // } elseif ($value === '3') {
                                //     $builder->where('cc_side', 'agent')
                                //         ->where('missed_call', true);
                                // }
                            }
                            if ($value === '3') {
                                $query->orWhere(function ($subQuery) {
                                    $subQuery->where('cc_side', 'member')
                                        ->where('voicemail_message', false)
                                        ->where('cc_cause', 'cancel')
                                        ->where('cc_cancel_reason', '<>', 'EXIT_WITH_KEY');
                                });
                            }
                            if ($value === '4') {
                                $query->orWhere(function ($subQuery) {
                                    $subQuery->where('missed_call', true)
                                        ->where('cc_side', 'agent')
                                        ->where(function ($subQuery1) {
                                            $subQuery1->where('hangup_cause', '!=', 'UNALLOCATED_NUMBER')
                                                ->orWhere('hangup_cause_q850', '!=', 1)
                                                ->orWhere('sip_hangup_disposition', '!=', 'recv_refuse');
                                        });
                                });
                            }
                        }
                        // $query->groupBy('start_epoch', 'destination_number', 'sip_call_id', 'cc_agent');
                        return $query;
                    });
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

    public function setDateRange($dateFrom, $dateTo)
    {
        $this->setFilter('date_from', $dateFrom);
        $this->setFilter('date_to', $dateTo);
    }

    public function dehydrate()
    {
        // $this->emit('initizalizePopovers');
        $this->dispatchBrowserEvent('initizalize-popovers');
    }
}
