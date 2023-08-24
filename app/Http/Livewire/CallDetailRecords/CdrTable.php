<?php

namespace App\Http\Livewire\CallDetailRecords;

use App\Models\CDR;
use Livewire\Component;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Filters\SelectFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\MultiSelectDropdownFilter;

class CdrTable extends DataTableComponent
{
    protected $model = CDR::class;

    public function configure(): void
    {
        $this->setPrimaryKey('xml_cdr_uuid');
        $this->setAdditionalSelects(['xml_cdr_uuid']);
        $this->setEmptyMessage('No results found');
    }

    public function columns(): array
    {
        return [
            Column::make('Caller ID', 'caller_id_number')
                ->sortable(),
            Column::make('Destination', 'caller_destination')
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
            // MultiSelectDropdownFilter::make('Tags')
            //     ->options(
            //         Tag::query()
            //             ->orderBy('name')
            //             ->get()
            //             ->keyBy('id')
            //             ->map(fn ($tag) => $tag->name)
            //             ->toArray()
            //     )
            //     ->setFirstOption('All Tags'),
        ];
    }
}
