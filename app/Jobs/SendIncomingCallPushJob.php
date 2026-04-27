<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Models\Extensions;
use App\Services\ApnsPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendIncomingCallPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;
    public $backoff = [5, 10];

    public function __construct(
        private array $data,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(ApnsPushService $apns): void
    {
        $extensionUuid = $this->data['extension_uuid'] ?? null;
        $extensionNumber = $this->data['extension_number'] ?? null;
        $domainName = $this->data['domain_name'] ?? null;
        $callerIdName = $this->data['caller_id_name'] ?? 'Unknown';
        $callerIdNumber = $this->data['caller_id_number'] ?? '';
        $callUuid = $this->data['call_uuid'] ?? '';
        $didPrefix = $this->data['did_prefix'] ?? '';
        $didE164 = $this->data['did_e164'] ?? '';

        $extension = null;
        if ($extensionUuid) {
            $extension = Extensions::where('extension_uuid', $extensionUuid)->first();
        }
        if (!$extension && $extensionNumber && $domainName) {
            $domain = Domain::where('domain_name', $domainName)->first();
            if ($domain) {
                $extension = Extensions::where('domain_uuid', $domain->domain_uuid)
                    ->where('extension', $extensionNumber)
                    ->first();
            }
        }
        if (!$extension) {
            Log::warning('[IncomingCallPush] Extension not found', $this->data);
            return;
        }

        if (!$extension->apns_voip_token) {
            Log::info('[IncomingCallPush] No VoIP token for extension', [
                'extension_uuid' => $extension->extension_uuid,
            ]);
            return;
        }

        // Optional caller-ID enrichment. Consumers can listen for an event
        // before this job is dispatched (or override this job) to attach
        // CRM data. Left null here so the upstream pipeline has no external
        // dependencies.
        $enrichment = null;

        $success = $apns->sendIncomingCallPush(
            $extension->apns_voip_token,
            $callerIdName,
            $callerIdNumber,
            $callUuid,
            $didPrefix,
            $didE164,
            $enrichment,
        );

        if (!$success) {
            Log::warning('[IncomingCallPush] Failed to send push', [
                'extension_uuid' => $extensionUuid,
            ]);
        }
    }
}
