<?php

namespace App\Exports;

use App\Models\Extensions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExtensionsExport implements FromCollection, WithHeadings
{
    protected Collection $rows;

    public function __construct()
    {
        $domainUuid = session('domain_uuid');

        // Sort & filter parity with your page
        $sortField = request()->get('sortField', 've.extension');
        $sortOrder = request()->get('sortOrder', 'asc');
        $filter    = request('filter', []);

        $builder = Extensions::query()
            ->from('v_extensions as ve')
            ->where('ve.domain_uuid', $domainUuid)
            ->select([
                've.extension',
                've.directory_first_name',
                've.directory_last_name',
                've.outbound_caller_id_number',
                've.emergency_caller_id_number',
                've.description',
            ])
            ->orderBy($sortField, $sortOrder);

        // Same search semantics you had in the controller
        if (!empty($filter['search'])) {
            $value = $filter['search'];
            $builder->where(function ($q) use ($value, $domainUuid) {
                $q->where('ve.extension', 'ilike', "%{$value}%")
                  ->orWhere('ve.effective_caller_id_name', 'ilike', "%{$value}%")
                  ->orWhere('ve.outbound_caller_id_number', 'ilike', "%{$value}%")
                  ->orWhere('ve.emergency_caller_id_number', 'ilike', "%{$value}%")
                  ->orWhere('ve.directory_first_name', 'ilike', "%{$value}%")
                  ->orWhere('ve.directory_last_name', 'ilike', "%{$value}%")
                  ->orWhere('ve.description', 'ilike', "%{$value}%")
                  ->orWhereExists(function ($sq) use ($value, $domainUuid) {
                      $sq->from('v_voicemails as vv')
                        ->whereColumn('vv.domain_uuid', 've.domain_uuid')
                        ->whereColumn('vv.voicemail_id', 've.extension')
                        ->where('vv.voicemail_mail_to', 'ilike', "%{$value}%");
                  });
            });
        }

        $rows = $builder->get();

        // Build a map: extension → email (no join; clean and fast)
        $exts = $rows->pluck('extension')->filter()->values();
        $emailMap = collect();
        if ($exts->isNotEmpty()) {
            $emailMap = DB::table('v_voicemails')
                ->select('voicemail_id', 'voicemail_mail_to')
                ->where('domain_uuid', $domainUuid)
                ->whereIn('voicemail_id', $exts)
                ->pluck('voicemail_mail_to', 'voicemail_id'); // [ext => email]
        }

$this->rows = $rows->map(function ($r) use ($emailMap) {
    $email = (string) ($emailMap->get($r->extension) ?? '');
    $cid   = formatPhoneNumber($r->outbound_caller_id_number);
    $emergencycid   = formatPhoneNumber($r->emergency_caller_id_number);

    return [
        'extension'           => $r->extension,
        'first_name'          => $r->directory_first_name,
        'last_name'           => $r->directory_last_name,
        'email'               => $email,
        'outbound_caller_id_number' => $cid,
        'emergency_caller_id_number' => $emergencycid,
        'description'         => $r->description,
    ];
});
    }

    public function headings(): array
    {
    return [
        'extension',
        'first_name',
        'last_name',
        'email',
        'outbound_caller_id_number',
        'emergency_caller_id_number',
        'description',
    ];
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

}