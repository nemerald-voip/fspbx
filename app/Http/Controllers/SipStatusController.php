<?php

namespace App\Http\Controllers;

use App\Models\FusionCache;
use App\Models\Gateways;
use App\Models\SipProfiles;
use App\Services\FreeswitchEslService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use SimpleXMLElement;
use Throwable;

class SipStatusController extends Controller
{
    protected string $viewName = 'SipStatus';

    protected array $profileInfoFields = [
        'name',
        'domain-name',
        'auto-nat',
        'db-name',
        'pres-hosts',
        'dialplan',
        'context',
        'challenge-realm',
        'rtp-ip',
        'ext-rtp-ip',
        'sip-ip',
        'ext-sip-ip',
        'url',
        'bind-url',
        'tls-url',
        'tls-bind-url',
        'hold-music',
        'outbound-proxy',
        'inbound-codecs',
        'outbound-codecs',
        'tel-event',
        'dtmf-mode',
        'cng',
        'session-to',
        'max-dialog',
        'nomedia',
        'late-neg',
        'proxy-media',
        'aggressive-nat',
        'stun-enabled',
        'stun-auto-disable',
        'user-agent-filter',
        'max-registrations-per-extension',
        'calls-in',
        'calls-out',
        'failed-calls-in',
        'failed-calls-out',
    ];

    public function index()
    {
        if (! $this->canViewPage()) {
            return redirect('/');
        }

        return Inertia::render($this->viewName, [
            'routes' => [
                'current_page' => route('sip-status.index'),
                'data_route' => route('sip-status.data'),
                'action' => route('sip-status.action'),
            ],
            'permissions' => $this->getPermissions(),
        ]);
    }

    public function data(FreeswitchEslService $eslService): JsonResponse
    {
        if (! $this->canViewPage()) {
            return response()->json([
                'errors' => ['auth' => ['Access denied.']],
            ], 403);
        }

        if (! $eslService->isConnected()) {
            return response()->json([
                'connected' => false,
                'generated_at' => now()->toIso8601String(),
                'summary' => [],
                'profiles' => [],
                'switch_status' => null,
                'errors' => ['event_socket' => ['Unable to connect to the FreeSWITCH event socket.']],
            ], 503);
        }

        try {
            $hostname = trim((string) $eslService->executeCommand('switchname', false));
            $sipProfiles = $this->getSipProfiles($hostname);
            $gateways = $this->getGateways();

            $summary = [];
            if ($this->canViewSofiaStatus()) {
                $summary = $this->getSummary($eslService, $sipProfiles, $gateways);
            }

            $profiles = [];
            if ($this->canViewProfileStatus()) {
                $profiles = $this->getProfileDetails($eslService, $sipProfiles);
            }

            return response()->json([
                'connected' => true,
                'generated_at' => now()->toIso8601String(),
                'summary' => $summary,
                'profiles' => $profiles,
                'switch_status' => $this->canViewSwitchStatus()
                    ? trim((string) $eslService->executeCommand('status', false))
                    : null,
            ]);
        } catch (Throwable $e) {
            logger('SipStatusController@data error: '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());

            return response()->json([
                'errors' => ['server' => ['Unable to load SIP status.']],
            ], 500);
        } finally {
            $eslService->disconnect();
        }
    }

    public function action(Request $request, FreeswitchEslService $eslService): JsonResponse
    {
        if (! $this->canRunCommands()) {
            return response()->json([
                'errors' => ['auth' => ['Access denied.']],
            ], 403);
        }

        $validated = $request->validate([
            'action' => 'required|string|in:killgw,start,stop,restart,flush_inbound_reg,rescan,cache-flush,reloadxml,reloadacl',
            'profile' => 'nullable|string',
            'gateway' => 'nullable|string',
        ]);

        try {
            $result = match ($validated['action']) {
                'cache-flush' => $this->flushCache(),
                'reloadxml' => $this->executeCommand($eslService, 'reloadxml'),
                'reloadacl' => $this->executeCommand($eslService, 'reloadacl'),
                default => $this->executeProfileCommand($validated, $eslService),
            };

            session(['reload_xml' => false]);

            return response()->json([
                'messages' => [
                    'success' => [filled($result) ? $result : 'Request successfully processed.'],
                ],
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            logger('SipStatusController@action error: '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());

            return response()->json([
                'errors' => ['server' => [$e->getMessage() ?: 'Unable to process request.']],
            ], 500);
        } finally {
            $eslService->disconnect();
        }
    }

    protected function getSummary(FreeswitchEslService $eslService, array $sipProfiles, array $gateways): array
    {
        $rows = [];
        $profileMap = collect($sipProfiles)->keyBy('sip_profile_name');

        $xml = $this->xmlResponse($eslService->executeCommand('sofia xmlstatus', false));
        if ($xml && isset($xml->profile)) {
            foreach ($xml->profile as $row) {
                $profileName = (string) $row->name;
                $profile = $profileMap->get($profileName);

                $rows[] = [
                    'id' => 'profile-'.$profileName,
                    'name' => $profileName,
                    'type' => ((string) $row->type) === 'profile' ? 'Profile' : (string) $row->type,
                    'data' => (string) $row->data,
                    'state' => (string) $row->state,
                    'action' => null,
                    'edit_url' => $profile && $this->canEditSipProfiles()
                        ? url('/app/sip_profiles/sip_profile_edit.php?id='.$profile['sip_profile_uuid'])
                        : null,
                ];
            }
        }

        $xmlGateways = $this->xmlResponse($eslService->executeCommand('sofia xmlstatus gateway', false));
        if ($xmlGateways && isset($xmlGateways->gateway)) {
            foreach ($xmlGateways->gateway as $row) {
                $gatewayUuid = strtolower((string) $row->name);
                $gateway = $gateways[$gatewayUuid] ?? null;
                $gatewayName = $gateway['gateway'] ?? $gatewayUuid;
                $gatewayDomain = $gateway['domain_name'] ?? null;
                $sameDomain = filled($gatewayDomain) && $gatewayDomain === session('domain_name');

                $rows[] = [
                    'id' => 'gateway-'.$gatewayUuid,
                    'name' => $gatewayDomain ? $gatewayName.'@'.$gatewayDomain : $gatewayName,
                    'type' => 'Gateway',
                    'data' => (string) $row->to,
                    'state' => (string) $row->state,
                    'action' => $this->canRunCommands() ? [
                        'label' => 'Stop',
                        'action' => 'killgw',
                        'profile' => (string) $row->profile,
                        'gateway' => $gateway['gateway_uuid'] ?? null,
                    ] : null,
                    'edit_url' => $sameDomain
                        ? url('/app/gateways/gateway_edit.php?id='.$gatewayUuid)
                        : null,
                ];
            }
        }

        if ($xml && isset($xml->alias)) {
            foreach ($xml->alias as $row) {
                $name = (string) $row->name;
                $rows[] = [
                    'id' => 'alias-'.$name,
                    'name' => $name,
                    'type' => (string) $row->type,
                    'data' => (string) $row->data,
                    'state' => (string) $row->state,
                    'action' => null,
                    'edit_url' => null,
                ];
            }
        }

        return $rows;
    }

    protected function getProfileDetails(FreeswitchEslService $eslService, array $sipProfiles): array
    {
        $profiles = [];

        foreach ($sipProfiles as $sipProfile) {
            $name = $sipProfile['sip_profile_name'];
            $xml = $this->xmlResponse($eslService->executeCommand("sofia xmlstatus profile '".$this->escapeSofiaArgument($name)."'", false));
            $state = $xml ? 'running' : 'stopped';
            $info = $xml ? ($xml->{'profile-info'} ?? $xml->profile_info ?? null) : null;

            $profiles[] = [
                'sip_profile_uuid' => $sipProfile['sip_profile_uuid'],
                'sip_profile_name' => $name,
                'state' => $state,
                'registration_count' => $this->getRegistrationCount($eslService, $name),
                'registrations_url' => route('registrations.index'),
                'details' => collect($this->profileInfoFields)->map(fn ($field) => [
                    'label' => $field,
                    'value' => $info ? (string) $info->{$field} : '',
                ])->values(),
            ];
        }

        return $profiles;
    }

    protected function getRegistrationCount(FreeswitchEslService $eslService, string $profileName): int
    {
        $xml = $this->xmlResponse($eslService->executeCommand("sofia xmlstatus profile '".$this->escapeSofiaArgument($profileName)."' reg", false));
        if (! $xml || ! isset($xml->registrations->registration)) {
            return 0;
        }

        $count = 0;
        $domainName = session('domain_name');

        foreach ($xml->registrations->registration as $registration) {
            $realm = (string) $registration->{'sip-auth-realm'};
            $userParts = explode('@', (string) $registration->user);

            if ($realm === $domainName || ($userParts[1] ?? null) === $domainName) {
                $count++;
            }
        }

        return $count;
    }

    protected function getSipProfiles(?string $hostname): array
    {
        return SipProfiles::query()
            ->select(['sip_profile_uuid', 'sip_profile_name'])
            ->where('sip_profile_enabled', 'true')
            ->when(filled($hostname), function ($query) use ($hostname) {
                $query->where(function ($nested) use ($hostname) {
                    $nested->where('sip_profile_hostname', $hostname)
                        ->orWhereNull('sip_profile_hostname')
                        ->orWhere('sip_profile_hostname', '');
                });
            })
            ->orderBy('sip_profile_name')
            ->get()
            ->map(fn (SipProfiles $profile) => [
                'sip_profile_uuid' => (string) $profile->sip_profile_uuid,
                'sip_profile_name' => (string) $profile->sip_profile_name,
            ])
            ->all();
    }

    protected function getGateways(): array
    {
        return Gateways::query()
            ->leftJoin('v_domains', 'v_domains.domain_uuid', '=', 'v_gateways.domain_uuid')
            ->get([
                'v_gateways.gateway_uuid',
                'v_gateways.gateway',
                'v_gateways.domain_uuid',
                'v_domains.domain_name',
            ])
            ->mapWithKeys(fn ($gateway) => [
                strtolower((string) $gateway->gateway_uuid) => [
                    'gateway_uuid' => (string) $gateway->gateway_uuid,
                    'gateway' => (string) $gateway->gateway,
                    'domain_uuid' => (string) $gateway->domain_uuid,
                    'domain_name' => $gateway->domain_name,
                ],
            ])
            ->all();
    }

    protected function executeProfileCommand(array $validated, FreeswitchEslService $eslService): string
    {
        $profileName = SipProfiles::query()
            ->where('sip_profile_name', $validated['profile'] ?? '')
            ->value('sip_profile_name');

        if (! $profileName) {
            throw ValidationException::withMessages([
                'profile' => ['Invalid SIP profile.'],
            ]);
        }

        $profile = "'".$this->escapeSofiaArgument($profileName)."'";

        $command = match ($validated['action']) {
            'killgw' => "sofia profile {$profile} killgw ".$this->getValidatedGatewayUuid($validated['gateway'] ?? null),
            'start' => "sofia profile {$profile} start",
            'stop' => "sofia profile {$profile} stop",
            'restart' => "sofia profile {$profile} restart",
            'flush_inbound_reg' => "sofia profile {$profile} flush_inbound_reg",
            'rescan' => "sofia profile {$profile} rescan",
        };

        return $this->executeCommand($eslService, $command);
    }

    protected function getValidatedGatewayUuid(?string $gatewayUuid): string
    {
        if (! $gatewayUuid) {
            throw ValidationException::withMessages([
                'gateway' => ['Invalid gateway.'],
            ]);
        }

        $gateway = Gateways::query()
            ->where('gateway_uuid', strtolower($gatewayUuid))
            ->value('gateway_uuid');

        if (! $gateway) {
            throw ValidationException::withMessages([
                'gateway' => ['Invalid gateway.'],
            ]);
        }

        return (string) $gateway;
    }

    protected function executeCommand(FreeswitchEslService $eslService, string $command): string
    {
        if (! $eslService->isConnected()) {
            throw new \RuntimeException('Unable to connect to the FreeSWITCH event socket.');
        }

        return trim((string) $eslService->executeCommand($command));
    }

    protected function flushCache(): string
    {
        return FusionCache::flushAll()
            ? '+OK cache flushed'
            : 'No cache method is configured or the cache could not be flushed.';
    }

    protected function xmlResponse($response): ?SimpleXMLElement
    {
        if ($response instanceof SimpleXMLElement) {
            return $response;
        }

        if (is_string($response)) {
            return $this->parseXml($response);
        }

        return null;
    }

    protected function parseXml(string $response): ?SimpleXMLElement
    {
        $response = trim($response);

        if ($response === '') {
            return null;
        }

        if (function_exists('iconv')) {
            $response = iconv('utf-8', 'utf-8//IGNORE', $response);
        }

        $response = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $response);
        $response = str_replace(['<profile-info>', '</profile-info>'], ['<profile_info>', '</profile_info>'], $response);

        try {
            $xml = simplexml_load_string($response);

            return $xml instanceof SimpleXMLElement ? $xml : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function escapeSofiaArgument(string $value): string
    {
        return addcslashes($value, "\\'");
    }

    protected function getPermissions(): array
    {
        return [
            'system_status_sofia_status' => $this->canViewSofiaStatus(),
            'system_status_sofia_status_profile' => $this->canViewProfileStatus(),
            'sip_status_switch_status' => $this->canViewSwitchStatus(),
            'sip_profile_edit' => $this->canEditSipProfiles(),
            'can_run_commands' => $this->canRunCommands(),
        ];
    }

    protected function canViewPage(): bool
    {
        return $this->canViewSofiaStatus()
            || $this->canViewProfileStatus()
            || $this->canViewSwitchStatus();
    }

    protected function canViewSofiaStatus(): bool
    {
        return userCheckPermission('system_status_sofia_status') || isSuperAdmin();
    }

    protected function canViewProfileStatus(): bool
    {
        return userCheckPermission('system_status_sofia_status_profile') || isSuperAdmin();
    }

    protected function canViewSwitchStatus(): bool
    {
        return userCheckPermission('sip_status_switch_status') || isSuperAdmin();
    }

    protected function canEditSipProfiles(): bool
    {
        return userCheckPermission('sip_profile_edit') || isSuperAdmin();
    }

    protected function canRunCommands(): bool
    {
        return isSuperAdmin();
    }
}
