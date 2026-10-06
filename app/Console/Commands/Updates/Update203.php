<?php

namespace App\Console\Commands\Updates;

use App\Services\RingotelApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;

class Update203
{
    public function getSupervisorProgramsToRestart(): array
    {
        return ['fs-cdr-service'];
    }

    public function apply(bool $dryRun = false): bool
    {
        if (! $this->updateGrandstreamSystemRing($dryRun)) {
            return false;
        }

        $counts = ['updated' => 0, 'would_update' => 0, 'correct' => 0, 'preserved' => 0, 'skipped' => 0, 'failed' => 0];

        try {
            // Match the Activated accounts on the Ringotel settings page.
            $accounts = DB::table('v_domain_settings as settings')
                ->join('v_domains as domains', 'domains.domain_uuid', '=', 'settings.domain_uuid')
                ->where('domains.domain_enabled', 'true')
                ->where('settings.domain_setting_category', 'app shell')
                ->where('settings.domain_setting_subcategory', 'org_id')
                ->where('settings.domain_setting_enabled', true)
                ->orderBy('domains.domain_name')
                ->get(['domains.domain_name', 'settings.domain_setting_value as org_id']);

            if ($accounts->isEmpty()) {
                echo "No activated Ringotel accounts; nothing to repair.\n";
                return true;
            }

            $service = app(RingotelApiService::class);

            // Keep this one-time repair inside the update. Decode JSON as objects
            // so a connection's empty objects and unknown settings survive intact.
            $request = function (string $method, array $params) use ($service): ?\stdClass {
                if (empty($service->getRingotelApiToken())) {
                    throw new \RuntimeException('Ringotel API token is missing.');
                }

                $response = Http::ringotel()
                    ->connectTimeout(10)
                    ->timeout(30)
                    ->post('/', ['method' => $method, 'params' => $params])
                    ->throw();

                // updateBranch is documented to return no data on success.
                if ($method === 'updateBranch' && trim($response->body()) === '') {
                    return null;
                }

                $body = json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);

                if ($method === 'updateBranch' && $body === null) {
                    return null;
                }

                if (! $body instanceof \stdClass) {
                    throw new \RuntimeException('Ringotel returned an invalid API response.');
                }

                if (isset($body->error)) {
                    throw new \RuntimeException('Ringotel API error: '.($body->error->message ?? 'Unknown error'));
                }

                if ($method === 'updateBranch') {
                    return null;
                }

                if (! ($body->result ?? null) instanceof \stdClass) {
                    throw new \RuntimeException('Ringotel did not return a connection.');
                }

                return $body->result;
            };

            $seen = [];

            foreach ($accounts as $account) {
                $orgId = trim((string) $account->org_id);

                if ($orgId === '') {
                    echo "Ringotel {$account->domain_name}: missing organization ID.\n";
                    $counts['failed']++;
                    continue;
                }

                // A paired organization may appear more than once locally.
                if (isset($seen[$orgId])) {
                    continue;
                }
                $seen[$orgId] = true;

                try {
                    $connections = $service->getConnections($orgId);
                } catch (Throwable $exception) {
                    if (strcasecmp(trim($exception->getMessage()), 'Organization not found') === 0) {
                        echo "Ringotel {$account->domain_name}: organization not found; skipped.\n";
                        $counts['skipped']++;
                        continue;
                    }

                    echo "Ringotel {$account->domain_name}: {$exception->getMessage()}\n";
                    $counts['failed']++;
                    continue;
                }

                echo "Ringotel {$account->domain_name}: checking {$connections->count()} connection(s).\n";

                foreach ($connections as $connection) {
                    try {
                        $connectionId = (string) $connection->id;
                        $params = ['orgid' => $orgId, 'id' => $connectionId];
                        $stored = $request('getBranch', $params);

                        if (($stored->id ?? null) !== $connectionId || ! ($stored->provision ?? null) instanceof \stdClass) {
                            throw new \RuntimeException('Ringotel returned an invalid connection.');
                        }

                        $provision = $stored->provision;
                        $on = $provision->dnd->on ?? null;
                        $off = $provision->dnd->off ?? null;

                        if ($on === '*78' && $off === '*79') {
                            $result = 'correct';
                        } elseif ($on !== '*79' || $off !== '*78') {
                            $result = 'preserved';
                        } elseif ($dryRun) {
                            $result = 'would_update';
                        } else {
                            $provision->dnd->on = '*78';
                            $provision->dnd->off = '*79';

                            // Ringotel does not document nested merge behavior. Send
                            // the current provision object with only these two edits.
                            $request('updateBranch', $params + ['provision' => $provision]);
                            $verified = $request('getBranch', $params);

                            if (($verified->id ?? null) !== $connectionId || ($verified->provision ?? null) != $provision) {
                                throw new \RuntimeException('Ringotel connection verification failed; inspect its DND codes and other settings before retrying.');
                            }

                            $result = 'updated';
                        }

                        $counts[$result]++;
                        $message = match ($result) {
                            'updated' => 'DND codes repaired and verified',
                            'would_update' => 'would repair DND to on=*78, off=*79',
                            'correct' => 'DND codes already correct',
                            'preserved' => 'custom or blank DND codes preserved',
                        };
                        echo "  Connection {$connection->id}: {$message}.\n";
                    } catch (Throwable $exception) {
                        echo "  Connection {$connection->id}: FAILED: {$exception->getMessage()}\n";
                        $counts['failed']++;
                    }
                }
            }
        } catch (Throwable $exception) {
            echo "Ringotel DND repair failed: {$exception->getMessage()}\n";
            return false;
        }

        echo "Ringotel DND repair: {$counts['updated']} repaired, {$counts['would_update']} would repair, "
            ."{$counts['correct']} already correct, {$counts['preserved']} preserved, "
            ."{$counts['skipped']} organization(s) skipped, {$counts['failed']} failed.\n";

        // API failures other than missing organizations must not mark the version complete. Successful repairs
        // are skipped on retry, including when a prior response was lost.
        return $counts['failed'] === 0;
    }

    private function updateGrandstreamSystemRing(bool $dryRun): bool
    {
        if ($dryRun) {
            echo "Would replace the invalid Grandstream system ring default of 0 in the settings catalog and database.\n";
            return true;
        }

        try {
            $path = public_path('app/grandstream/app_config.php');
            if (File::exists($path)) {
                $body = File::get($path);
                // Patch only the shipped invalid value; retain every other catalog entry.
                $pattern = '~(\[\x27default_setting_subcategory\x27\] = "grandstream_system_ring";\s*\$apps\[\$x\]\[\x27default_settings\x27\]\[\$y\]\[\x27default_setting_name\x27\] = "text";\s*\$apps\[\$x\]\[\x27default_settings\x27\]\[\$y\]\[\x27default_setting_value\x27\] = )"0";~';
                $updatedBody = preg_replace($pattern, '${1}"f1=440,f2=480,c=200/400;";', $body, 1, $count);
                if ($updatedBody === null) {
                    throw new \RuntimeException('Could not patch the Grandstream system ring definition.');
                }
                if ($count > 0) {
                    if (File::put($path, $updatedBody) !== strlen($updatedBody)) {
                        throw new \RuntimeException('Could not write the Grandstream settings catalog.');
                    }
                    echo "Grandstream system ring catalog default corrected.\n";
                }
            }

            // Preserve enabled flags, UUIDs, tenant overrides, and custom tone patterns.
            $updated = DB::table('v_default_settings')
                ->where('default_setting_category', 'provision')
                ->where('default_setting_subcategory', 'grandstream_system_ring')
                ->where('default_setting_name', 'text')
                ->where('default_setting_value', '0')
                ->update(['default_setting_value' => 'f1=440,f2=480,c=200/400;']);

            echo "Grandstream system ring: corrected {$updated} database default(s).\n";
            return true;
        } catch (Throwable $exception) {
            echo "Grandstream system ring update failed: {$exception->getMessage()}\n";
            return false;
        }
    }
}
