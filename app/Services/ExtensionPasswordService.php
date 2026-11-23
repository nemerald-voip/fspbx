<?php

namespace App\Services;

use App\Jobs\UpdateAppSettings;
use App\Models\Extensions;
use App\Models\MobileAppUsers;
use Illuminate\Support\Facades\DB;

class ExtensionPasswordService
{
    public function update(Extensions $extension, string $password): void
    {
        DB::transaction(function () use ($extension, $password) {
            $extension->password = $password;
            $extension->save();

            $mobileApps = MobileAppUsers::query()
                ->where('domain_uuid', $extension->domain_uuid)
                ->where('extension_uuid', $extension->extension_uuid)
                ->whereNotNull('org_id')
                ->where('org_id', '!=', '')
                ->whereNotNull('user_id')
                ->where('user_id', '!=', '')
                ->get(['org_id', 'user_id'])
                ->unique(fn ($app) => $app->org_id . ':' . $app->user_id);

            foreach ($mobileApps as $mobileApp) {
                // A password change must not activate a user or overwrite their profile.
                UpdateAppSettings::dispatch([
                    'user_id' => $mobileApp->user_id,
                    'org_id' => $mobileApp->org_id,
                    'password' => $extension->password,
                    'no_email' => true,
                ])->onQueue('default')->afterCommit();
            }
        });

        // The registration domain can differ from the extension's dialplan context (e.g. public).
        app(AgentDirectoryCacheService::class)->extensionsChanged([$extension->getAttributes()]);
    }
}
