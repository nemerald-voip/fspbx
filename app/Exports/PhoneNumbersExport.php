<?php

namespace App\Exports;

use App\Models\Destinations;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PhoneNumbersExport implements FromCollection, WithHeadings
{
    protected array $searchable = ['destination_number', 'destination_description', 'destination_actions'];
    protected Collection $rows;

    public function __construct()
    {
        $domainUuid = session('domain_uuid');
        $sortField  = request()->get('sortField', 'destination_number');
        $sortOrder  = request()->get('sortOrder', 'asc');

        $q = Destinations::query()
            ->where('domain_uuid', $domainUuid)
            ->select([
                'destination_prefix',
                'destination_number',
                'destination_actions',
                'destination_description',
            ])
            ->orderBy($sortField, $sortOrder);

        // Optional filterData.search (same pattern as ContactsExport)
        $filterData = request('filterData', []);
        if (!empty($filterData['search'])) {
            $value = $filterData['search'];
            $q->where(function ($query) use ($value) {
                foreach ($this->searchable as $field) {
                    $query->orWhere($field, 'ilike', '%'.$value.'%');
                }
            });
        }

  //      logger($q->get());

    $this->rows = $q->get()->map(function ($r) {
        return [
            'country_code' => $r->destination_prefix,
            // 'phone_number' => $this->formatForExport($r->destination_prefix, $r->destination_number),

            'phone_number'  => formatPhoneNumber($r->destination_number),
            'destination'  => $this->buildDestinationLabel($r),
            'description'  => $r->destination_description,
        ];
    });

    }

    public function headings(): array
    {
        // Match the spec (prefix, phone number, destination, description)
        return ['country_code', 'phone_number', 'destination', 'description'];
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

private function buildDestinationLabel($r): string
{
    $parts = [];
    $options = $r->routing_options ?? null;

    if (is_array($options) && !empty($options)) {
        foreach ($options as $opt) {
            $type = $opt['type']      ?? null;
            $ext  = $opt['extension'] ?? null;

            if ($type || $ext) {
                $labelBits = [];
                if (!empty($type)) {
                    $labelBits[] = "Type: {$type}";
                }
                if (!empty($ext)) {
                    $labelBits[] = "Extension: {$ext}";
                }
                // Add the formatted string to the main parts array
                $parts[] = implode(', ', $labelBits);
            }
        }
    }

    // Returns the joined string, or an empty string "" if $parts is empty
    return implode(' | ', $parts);
}



}

