<?php

use App\Services\OutboundCallerIdFixer;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $result = (new OutboundCallerIdFixer())->run();

        echo sprintf(
            "[OutboundCallerIdFixer] dialplans patched: %d; gateways patched: %d\n",
            $result['dialplans_patched'],
            $result['gateways_patched'],
        );
    }

    public function down(): void
    {
        // The forward patch converts a functionally-broken no-op into a working
        // dialplan block; there is no meaningful rollback.
    }
};
