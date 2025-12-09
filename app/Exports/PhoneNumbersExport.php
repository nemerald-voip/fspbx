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

        $this->rows = $q->get()->map(function ($r) {
            // Parse destination_actions JSON → "app:data | app:data"
            $dest = '';
            if (!empty($r->destination_actions)) {
                $arr = json_decode($r->destination_actions, true);
                if (is_array($arr)) {
                    $parts = [];
                    foreach ($arr as $a) {
                        $app  = $a['destination_app']  ?? '';
                        $data = $a['destination_data'] ?? '';
                        $parts[] = trim($app . ($data !== '' ? ':' . $data : ''));
                    }
                    $dest = implode(' | ', array_filter($parts));
                }
            }

            return [
                'prefix'       => $r->destination_prefix,
               // Normalize per rule:
                // - If prefix is "1" or "+1" → (xxx) xxx-xxxx
                // - OR if prefix is empty AND number starts with "+1" → (xxx) xxx-xxxx
                // - Else leave as-is
                'phone_number' => $this->formatForExport($r->destination_prefix, $r->destination_number),                'destination'  => $dest,
                'description'  => $r->destination_description,
            ];
        });
    }

    public function headings(): array
    {
        // Match the spec (prefix, phone number, destination, description)
        return ['prefix', 'phone_number', 'destination', 'description'];
    }

    public function collection(): Collection
    {
        return $this->rows;
    }


    /**
     * Normalize NANPA display for +1/1 cases only.
     * - If prefix is "1" or "+1" → format (xxx) xxx-xxxx
     * - If prefix is empty and number starts with "+1" → format (xxx) xxx-xxxx
     * - Otherwise, return the original number unchanged
     */
    private function formatForExport(?string $prefix, ?string $number): string
    {
        $p = trim((string) $prefix);
        $n = trim((string) $number);

        $shouldFormat =
            ($p === '1' || $p === '+1') ||
            ($p === '' && preg_match('/^\+1\d{10}$/', $n));

        if (!$shouldFormat) {
            return $n;
        }

        // Keep digits only; drop leading country code if present
        $digits = preg_replace('/\D+/', '', $n);
        if (strlen($digits) === 11 && $digits[0] === '1') {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) !== 10) {
            // Bad shape; don't mangle
            return $n;
        }

        $area = substr($digits, 0, 3);
        $pref = substr($digits, 3, 3);
        $line = substr($digits, 6, 4);
        return "({$area}) {$pref}-{$line}";
    }
}

