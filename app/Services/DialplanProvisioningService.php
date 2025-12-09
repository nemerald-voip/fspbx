<?php

namespace App\Services;

use SimpleXMLElement;
use App\Models\Domain;
use App\Models\Dialplans;
use Illuminate\Support\Str;
use App\Models\DefaultSettings;
use App\Models\DialplanDetails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DialplanProvisioningService
{
    /**
     * Bootstrap stock dialplans for a newly-created domain.
     *
     * This roughly mirrors FusionPBX dialplan->import(),
     * but only for ONE domain and using Laravel patterns.
     */
    public function bootstrapForDomain(Domain $domain): void
    {
        $templateDir = base_path('public/app/dialplans/resources/switch/conf/dialplan/');

        if (!$templateDir || !File::isDirectory($templateDir)) {
            logger('DialplanProvisioningService: dialplan template directory not found: ' . $templateDir);
            return;
        }

        $xmlFiles = File::glob($templateDir . '/*.xml');
        if (empty($xmlFiles)) {
            logger('DialplanProvisioningService: no dialplan templates found in ' . $templateDir);
            return;
        }

        // Get app_uuids already present for this domain (or global)
        $existingAppUuids = Dialplans::query()
            ->where(function ($q) use ($domain) {
                $q->where('domain_uuid', $domain->domain_uuid)
                    ->orWhereNull('domain_uuid');
            })
            ->whereNotNull('app_uuid')
            ->pluck('app_uuid')
            ->all();

        $insertDialplans = [];
        $insertDetails   = [];

        foreach ($xmlFiles as $xmlFile) {
            try {
                $this->buildFromTemplate(
                    $xmlFile,
                    $domain,
                    $existingAppUuids,
                    $insertDialplans,
                    $insertDetails
                );
            } catch (\Throwable $e) {
                logger('DialplanProvisioningService: failed to parse template ' . $xmlFile . ' - ' .
                    $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            }
        }

        if (empty($insertDialplans)) {
            // nothing new to insert
            return;
        }

        DB::transaction(function () use ($insertDialplans, $insertDetails) {
            // Insert dialplans
            Dialplans::insert($insertDialplans);

            // Insert details (in chunks to avoid giant single insert)
            foreach (array_chunk($insertDetails, 500) as $chunk) {
                DialplanDetails::insert($chunk);
            }
        });

        logger(sprintf(
            'DialplanProvisioningService: created %d dialplans / %d details for new domain %s',
            count($insertDialplans),
            count($insertDetails),
            $insertDialplans[0]['dialplan_context'] ?? 'n/a'
        ));
    }

    /**
     * Build a dialplan + its details from a single XML template.
     * Mutates $insertDialplans and $insertDetails arrays.
     */
    protected function buildFromTemplate(
        string $xmlFile,
        Domain $domain,
        array $existingAppUuids,
        array &$insertDialplans,
        array &$insertDetails
    ): void {
        $xmlString = File::get($xmlFile);
        if (!$xmlString) {
            return;
        }

        // PIN length – if you want this configurable, put it into config/default_settings
        $pinLength = 8;

        // Replace template variables
        $xmlString = str_replace('{v_context}', $domain->domain_name, $xmlString);
        $xmlString = str_replace('{v_pin_number}', $this->generateNumericPin($pinLength), $xmlString);

        // Parse XML
        /** @var SimpleXMLElement $xml */
        $xml = simplexml_load_string($xmlString);
        if (!$xml) {
            return;
        }

        $json     = json_encode($xml);
        $dialplan = json_decode($json, true);

        if (empty($dialplan) || !isset($dialplan['@attributes'])) {
            return;
        }

        $attrs    = $dialplan['@attributes'];
        $appUuid  = $attrs['app_uuid'] ?? null;

        // If this app_uuid already exists for this domain/global, skip (same behavior as Fusion)
        if ($appUuid && in_array($appUuid, $existingAppUuids, true)) {
            return;
        }

        // Global dialplans (context=global) don't belong to a specific domain_uuid
        $dialplanGlobal  = !empty($attrs['global']) && $attrs['global'] === 'true';
        $dialplanContext = $attrs['context'] ?? 'default';
        $dialplanContext = str_replace('${domain_name}', $domain->domain_name, $dialplanContext);

        $domainUuid = $dialplanGlobal ? null : $domain->domain_uuid;

        $dialplanUuid = (string) Str::uuid();

        // Build dialplan row
        $insertDialplans[] = [
            'dialplan_uuid'       => $dialplanUuid,
            'domain_uuid'         => $domainUuid,
            'app_uuid'            => $appUuid,
            'dialplan_name'       => $attrs['name'] ?? basename($xmlFile, '.xml'),
            'dialplan_number'     => $attrs['number'] ?? null,
            'dialplan_context'    => $dialplanContext,
            'dialplan_destination' => $attrs['destination'] ?? null,
            'dialplan_continue'   => $attrs['continue'] ?? null,
            'dialplan_order'      => $attrs['order'] ?? 0,
            'dialplan_enabled'    => $attrs['enabled'] ?? 'true',
            'dialplan_description' => $attrs['description'] ?? null,
            'dialplan_xml'         => $this->normalizeDialplanXml($xmlString),
        ];

        if (!empty($dialplan['condition']) && empty($dialplan['condition'][0])) {
            $tmp = $dialplan['condition'];
            unset($dialplan['condition']);
            $dialplan['condition'][0] = $tmp;
        }

        // Build details rows
        $group = 0;
        $order = 5;

        if (isset($dialplan['condition']) && is_array($dialplan['condition'])) {
            foreach ($dialplan['condition'] as $cond) {
                $condAttrs = $cond['@attributes'] ?? [];

                $insertDetails[] = [
                    'dialplan_detail_uuid'   => (string) Str::uuid(),
                    'domain_uuid'            => $domainUuid,
                    'dialplan_uuid'          => $dialplanUuid,
                    'dialplan_detail_tag'    => 'condition',
                    'dialplan_detail_order'  => $order,
                    'dialplan_detail_type'   => $condAttrs['field'] ?? null,
                    'dialplan_detail_data'   => $condAttrs['expression'] ?? null,
                    'dialplan_detail_break'  => $condAttrs['break'] ?? null,
                    'dialplan_detail_inline' => null,
                    'dialplan_detail_group'  => $group,
                    'dialplan_detail_enabled' => $condAttrs['enabled'] ?? 'true',
                ];

                $order += 5;

                // Normalize action/anti-action arrays
                if (!empty($cond['action']) && empty($cond['action'][0])) {
                    $tmp = $cond['action'];
                    unset($cond['action']);
                    $cond['action'][0] = $tmp;
                }
                if (!empty($cond['anti-action']) && empty($cond['anti-action'][0])) {
                    $tmp = $cond['anti-action'];
                    unset($cond['anti-action']);
                    $cond['anti-action'][0] = $tmp;
                }

                // Actions
                if (!empty($cond['action']) && is_array($cond['action'])) {
                    foreach ($cond['action'] as $act) {
                        $actAttrs = $act['@attributes'] ?? [];

                        $insertDetails[] = [
                            'dialplan_detail_uuid'   => (string) Str::uuid(),
                            'domain_uuid'            => $domainUuid,
                            'dialplan_uuid'          => $dialplanUuid,
                            'dialplan_detail_tag'    => 'action',
                            'dialplan_detail_order'  => $order,
                            'dialplan_detail_type'   => $actAttrs['application'] ?? null,
                            'dialplan_detail_data'   => $actAttrs['data'] ?? null,
                            'dialplan_detail_break'  => null,
                            'dialplan_detail_inline' => $actAttrs['inline'] ?? null,
                            'dialplan_detail_group'  => $group,
                            'dialplan_detail_enabled' => $actAttrs['enabled'] ?? 'true',
                        ];

                        $order += 5;
                    }
                }

                // Anti-actions
                if (!empty($cond['anti-action']) && is_array($cond['anti-action'])) {
                    foreach ($cond['anti-action'] as $act) {
                        $actAttrs = $act['@attributes'] ?? [];

                        $insertDetails[] = [
                            'dialplan_detail_uuid'   => (string) Str::uuid(),
                            'domain_uuid'            => $domainUuid,
                            'dialplan_uuid'          => $dialplanUuid,
                            'dialplan_detail_tag'    => 'anti-action',
                            'dialplan_detail_order'  => $order,
                            'dialplan_detail_type'   => $actAttrs['application'] ?? null,
                            'dialplan_detail_data'   => $actAttrs['data'] ?? null,
                            'dialplan_detail_break'  => null,
                            'dialplan_detail_inline' => $actAttrs['inline'] ?? null,
                            'dialplan_detail_group'  => $group,
                            'dialplan_detail_enabled' => $actAttrs['enabled'] ?? 'true',
                        ];

                        $order += 5;
                    }
                }

                // Increment group if there were any actions
                if (!empty($cond['action']) || !empty($cond['anti-action'])) {
                    $group++;
                }

                // Step order for next condition
                $order += 5;
            }
        }
    }

    /**
     * Very simple numeric PIN generator to mirror generate_password() for digits.
     */
    protected function generateNumericPin(int $length = 8): string
    {
        $digits = '';
        for ($i = 0; $i < $length; $i++) {
            $digits .= random_int(0, 9);
        }

        return $digits;
    }

    /**
     * Clean up the template XML so it’s safe to be stored in dialplan_xml.
     * - strips the XML declaration if present
     * - trims whitespace
     */
    protected function normalizeDialplanXml(string $xmlString): string
    {
        // remove XML declaration if present
        $xmlString = preg_replace('/^<\?xml[^>]*\?>\s*/', '', $xmlString);

        // you can add more normalization here if needed later
        return trim($xmlString);
    }


    public function ensureSwitchDirectories(string $domainName): void
    {
        // recordings base dir
        $recordingsDir = DefaultSettings::where('default_setting_category', 'switch')
            ->where('default_setting_subcategory', 'recordings')
            ->where('default_setting_name', 'dir')
            ->pluck('default_setting_value')
            ->first();

        if ($recordingsDir) {
            $path = rtrim($recordingsDir, '/') . '/' . $domainName;
            if (!File::exists($path)) {
                File::makeDirectory($path, 0770, true);
            }
        }

        // voicemail base dir (…/voicemail/default/{domain})
        $voicemailDir = DefaultSettings::where('default_setting_category', 'switch')
            ->where('default_setting_subcategory', 'voicemail')
            ->where('default_setting_name', 'dir')
            ->pluck('default_setting_value')
            ->first();

        if ($voicemailDir) {
            $path = rtrim($voicemailDir, '/') . '/default/' . $domainName;
            if (!File::exists($path)) {
                File::makeDirectory($path, 0770, true);
            }
        }
    }
}
