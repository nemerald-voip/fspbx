<?php

namespace App\Services;

use App\Models\DefaultSettings;
use App\Services\Ha\ActiveNodeResolver;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Throwable;

class S3UploadServerSelector
{
    public function __construct(private readonly ActiveNodeResolver $activeNode) {}

    public function resolve(?array $settings = null): array
    {
        $settings ??= DefaultSettings::query()
            ->where('default_setting_category', 'scheduled_jobs')
            ->where('default_setting_enabled', true)
            ->pluck('default_setting_value', 'default_setting_subcategory')->all();

        // Until the shared switch is configured, retain existing enablement.
        $enabled = array_key_exists('s3_upload_calls', $settings)
            ? $settings['s3_upload_calls'] === 'true'
            : collect($settings)->contains(fn ($value, $key) =>
                preg_match('/^s3_upload_calls_(?:[0-9a-f]{2}:){5}[0-9a-f]{2}$/i', $key) && $value === 'true');

        if (! $enabled) {
            return ['allowed' => false, 'coordinated' => false, 'reason' => 'S3 uploads are disabled.'];
        }

        try {
            if ($this->coordinationAvailable()) {
                $decision = $this->activeNode->resolve();
                // Standby and draining are definite decisions too.
                if (in_array($decision['status'], ['active', 'standby', 'draining'], true)) {
                    return [
                        'allowed' => (bool) $decision['active'],
                        'coordinated' => true,
                        'reason' => $decision['reason'],
                    ];
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        $mac = $this->macAddress();

        return [
            'allowed' => $mac !== null && ($settings['s3_upload_calls_'.$mac] ?? null) === 'true',
            'coordinated' => false,
            'reason' => 'Scheduled-job ownership is unavailable or uncertain; using the legacy MAC selection.',
        ];
    }

    protected function coordinationAvailable(): bool
    {
        return Schema::hasTable('scheduled_job_nodes')
            && Schema::hasTable('scheduled_job_handoffs')
            && Schema::hasTable('scheduled_job_executions');
    }

    public function macAddress(): ?string
    {
        // Preserve the scheduler/seeder's existing interface selection.
        $mac = trim(Process::run("ip link show | grep 'link/ether' | awk '{print $2}' | head -n 1")->output());

        return $mac ?: null;
    }
}
