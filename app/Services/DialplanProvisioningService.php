<?php

namespace App\Services;

use App\Models\DefaultSettings;
use App\Models\DialplanDetails;
use App\Models\Dialplans;
use App\Models\Domain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SimpleXMLElement;

class DialplanProvisioningService
{
    /**
     * Bootstrap stock dialplans for a newly-created domain.
     *
     */
    public function bootstrapForDomain(Domain $domain): void
    {
        $templateDir = base_path('public/app/dialplans/resources/switch/conf/dialplan/');

        if (!File::isDirectory($templateDir)) {
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
                logger(
                    'DialplanProvisioningService: failed to parse template ' . $xmlFile . ' - ' .
                    $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine()
                );
            }
        }

        if (empty($insertDialplans)) {
            return;
        }

        DB::transaction(function () use ($insertDialplans, $insertDetails) {
            Dialplans::insert($insertDialplans);

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

        // PIN length – configurable if you want
        $pinLength = 8;

        // Replace template variables
        $xmlString = str_replace('{v_context}', $domain->domain_name, $xmlString);
        $xmlString = str_replace('{v_pin_number}', $this->generateNumericPin($pinLength), $xmlString);

        /** @var SimpleXMLElement|false $xml */
        $xml = simplexml_load_string($xmlString);
        if (!$xml) {
            return;
        }

        $dialplan = json_decode(json_encode($xml), true);

        if (empty($dialplan) || !isset($dialplan['@attributes'])) {
            return;
        }

        $attrs   = $dialplan['@attributes'];
        $appUuid = $attrs['app_uuid'] ?? null;

        // Same behavior as Fusion: skip if this app_uuid already exists for domain/global
        if ($appUuid && in_array($appUuid, $existingAppUuids, true)) {
            return;
        }

        // Global dialplans don't belong to a specific domain_uuid
        $dialplanGlobal  = !empty($attrs['global']) && $attrs['global'] === 'true';
        $dialplanContext = $attrs['context'] ?? 'default';
        $dialplanContext = str_replace('${domain_name}', $domain->domain_name, $dialplanContext);

        $domainUuid   = $dialplanGlobal ? null : $domain->domain_uuid;
        $dialplanUuid = (string) Str::uuid();

        // Build dialplan row (IMPORTANT: dialplan_xml must be FusionPBX-style)
        $insertDialplans[] = [
            'dialplan_uuid'        => $dialplanUuid,
            'domain_uuid'          => $domainUuid,
            'app_uuid'             => $appUuid,
            'dialplan_name'        => $attrs['name'] ?? basename($xmlFile, '.xml'),
            'dialplan_number'      => $attrs['number'] ?? null,
            'dialplan_context'     => $dialplanContext,
            'dialplan_destination' => $attrs['destination'] ?? null,
            'dialplan_continue'    => $attrs['continue'] ?? null,
            'dialplan_order'       => $attrs['order'] ?? 0,
            'dialplan_enabled'     => $attrs['enabled'] ?? 'true',
            'dialplan_description' => $attrs['description'] ?? null,
            'dialplan_xml'         => $this->normalizeDialplanXml($xmlString, $dialplanUuid),
        ];

        // Ensure the condition array is uniform
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
                    'dialplan_detail_uuid'    => (string) Str::uuid(),
                    'domain_uuid'             => $domainUuid,
                    'dialplan_uuid'           => $dialplanUuid,
                    'dialplan_detail_tag'     => 'condition',
                    'dialplan_detail_order'   => $order,
                    'dialplan_detail_type'    => $condAttrs['field'] ?? null,
                    'dialplan_detail_data'    => $condAttrs['expression'] ?? null,
                    'dialplan_detail_break'   => $condAttrs['break'] ?? null,
                    'dialplan_detail_inline'  => null,
                    'dialplan_detail_group'   => $group,
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
                            'dialplan_detail_uuid'    => (string) Str::uuid(),
                            'domain_uuid'             => $domainUuid,
                            'dialplan_uuid'           => $dialplanUuid,
                            'dialplan_detail_tag'     => 'action',
                            'dialplan_detail_order'   => $order,
                            'dialplan_detail_type'    => $actAttrs['application'] ?? null,
                            'dialplan_detail_data'    => $actAttrs['data'] ?? null,
                            'dialplan_detail_break'   => null,
                            'dialplan_detail_inline'  => $actAttrs['inline'] ?? null,
                            'dialplan_detail_group'   => $group,
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
                            'dialplan_detail_uuid'    => (string) Str::uuid(),
                            'domain_uuid'             => $domainUuid,
                            'dialplan_uuid'           => $dialplanUuid,
                            'dialplan_detail_tag'     => 'anti-action',
                            'dialplan_detail_order'   => $order,
                            'dialplan_detail_type'    => $actAttrs['application'] ?? null,
                            'dialplan_detail_data'    => $actAttrs['data'] ?? null,
                            'dialplan_detail_break'   => null,
                            'dialplan_detail_inline'  => $actAttrs['inline'] ?? null,
                            'dialplan_detail_group'   => $group,
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
     * Normalize template XML into dialplan_xml:
     */
    protected function normalizeDialplanXml(string $xmlString, string $dialplanUuid): string
    {
        $xmlString = preg_replace('/^<\?xml[^>]*\?>\s*/', '', $xmlString);
        $xmlString = trim($xmlString);

        if ($xmlString === '') {
            return '';
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        // Suppress warnings for slightly imperfect templates
        if (!@$dom->loadXML($xmlString)) {
            return $xmlString; // fallback (better than breaking inserts)
        }

        $xpath = new \DOMXPath($dom);

        // Remove any node with enabled="false" (actions, conditions, etc.)
        foreach ($xpath->query('//*[@enabled="false"]') as $node) {
            $node->parentNode?->removeChild($node);
        }

        // Strip enabled="true" attributes (optional but matches FusionPBX dialplan_xml style)
        foreach ($xpath->query('//*[@enabled="true"]') as $node) {
            if ($node instanceof \DOMElement) {
                $node->removeAttribute('enabled');
            }
        }

        // Find the <extension> root element
        $extension = $xpath->query('/extension')->item(0);

        if ($extension instanceof \DOMElement) {
            // Force uuid
            $extension->setAttribute('uuid', $dialplanUuid);

            // Remove template/meta attrs Fusion doesn't store on <extension>
            foreach (['number', 'context', 'app_uuid', 'order', 'destination', 'global', 'enabled'] as $attr) {
                if ($extension->hasAttribute($attr)) {
                    $extension->removeAttribute($attr);
                }
            }

            // Keep only name/continue/uuid on extension
            $allowed = ['name', 'continue', 'uuid'];
            $toRemove = [];
            foreach ($extension->attributes as $attrNode) {
                if (!in_array($attrNode->nodeName, $allowed, true)) {
                    $toRemove[] = $attrNode->nodeName;
                }
            }
            foreach ($toRemove as $attrName) {
                $extension->removeAttribute($attrName);
            }
        }

        // Ensure every <action> and <anti-action> has data="" attribute (Fusion-style)
        foreach ($xpath->query('//action | //anti-action') as $node) {
            if ($node instanceof \DOMElement) {
                if (!$node->hasAttribute('data')) {
                    $node->setAttribute('data', '');
                }
            }
        }

        // Return without XML declaration
        $out = $dom->saveXML($dom->documentElement);
        return trim($out ?: '');
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
