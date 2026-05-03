<?php

namespace App\Console\Commands;

use App\Services\OutboundCallerIdFixer;
use Illuminate\Console\Command;

class FixOutboundCid extends Command
{
    protected $signature = 'pbx:fix-outbound-cid';

    protected $description = 'Patch OUTBOUND_CALLER_ID dialplans and enable caller_id_in_from on gateways so extension CIDs reach upstream SIP trunks. Safe to rerun on every deploy.';

    public function handle(OutboundCallerIdFixer $fixer): int
    {
        $result = $fixer->run();

        $this->info(sprintf(
            'dialplans patched: %d; gateways patched: %d',
            $result['dialplans_patched'],
            $result['gateways_patched'],
        ));

        return self::SUCCESS;
    }
}
