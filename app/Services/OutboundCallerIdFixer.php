<?php

namespace App\Services;

use App\Models\FusionCache;
use Illuminate\Support\Facades\DB;

/**
 * Patches FusionPBX defaults that prevent outbound caller ID from reaching
 * upstream SIP trunks.
 *
 * The stock FusionPBX OUTBOUND_CALLER_ID dialplan ends with an idempotent
 * no-op ({@code set outbound_caller_id_number=${outbound_caller_id_number}})
 * that never copies the extension's configured CID onto effective_caller_id
 * or exports it as PAI. Combined with gateways shipping with
 * {@code caller_id_in_from} unset (defaulting to false), the outbound INVITE
 * carries only the gateway auth-user in From and the extension number in
 * Remote-Party-ID — carriers then substitute their default trunk CLI.
 *
 * This runs at every deploy (via Ansible) so newly-provisioned domains get
 * patched without a manual step.
 */
class OutboundCallerIdFixer
{
    public const BROKEN_ACTION_PATTERN =
        '/(<condition field="\$\{outbound_caller_id_number\}" expression="\^\\\\d\{6,25\}\$" break="never">\s*\n)'
        . '(\s*)<action application="set" data="outbound_caller_id_number=\$\{outbound_caller_id_number\}" inline="true"\/>/';

    public const REPLACEMENT_FORMAT = "%s%s<action application=\"set\" data=\"effective_caller_id_number=\${outbound_caller_id_number}\" inline=\"true\"/>\n%s<action application=\"set\" data=\"effective_caller_id_name=\${outbound_caller_id_name}\" inline=\"true\"/>\n%s<action application=\"export\" data=\"sip_h_P-Asserted-Identity=<sip:\${outbound_caller_id_number}@\${domain_name}>\"/>";

    /**
     * @return array{dialplans_patched: int, gateways_patched: int}
     */
    public function run(): array
    {
        return [
            'dialplans_patched' => $this->patchDialplans(),
            'gateways_patched' => $this->patchGateways(),
        ];
    }

    protected function patchDialplans(): int
    {
        $rows = DB::table('v_dialplans')
            ->where('dialplan_name', 'OUTBOUND_CALLER_ID')
            ->get(['dialplan_uuid', 'dialplan_xml']);

        $patched = 0;

        foreach ($rows as $row) {
            $xml = $row->dialplan_xml;

            if ($xml === null || $xml === '') {
                continue;
            }

            // Already patched — effective_caller_id_number assignment is present.
            if (strpos($xml, 'effective_caller_id_number=${outbound_caller_id_number}') !== false) {
                continue;
            }

            $count = 0;
            $newXml = preg_replace_callback(
                self::BROKEN_ACTION_PATTERN,
                fn ($m) => sprintf(self::REPLACEMENT_FORMAT, $m[1], $m[2], $m[2], $m[2]),
                $xml,
                -1,
                $count,
            );

            if ($count > 0) {
                DB::table('v_dialplans')
                    ->where('dialplan_uuid', $row->dialplan_uuid)
                    ->update([
                        'dialplan_xml' => $newXml,
                        'update_date' => date('Y-m-d H:i:s'),
                    ]);
                $patched++;
            }
        }

        if ($patched > 0) {
            FusionCache::clear('dialplan:public');
        }

        return $patched;
    }

    protected function patchGateways(): int
    {
        return DB::table('v_gateways')
            ->where(function ($q) {
                $q->whereNull('caller_id_in_from')->orWhere('caller_id_in_from', '');
            })
            ->update([
                'caller_id_in_from' => 'true',
                'update_date' => date('Y-m-d H:i:s'),
            ]);
    }
}
