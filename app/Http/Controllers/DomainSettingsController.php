<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\BulkSettingsActionRequest;
use App\Http\Requests\Settings\SaveDomainSettingRequest;
use App\Models\Domain;
use App\Models\DomainSettings;
use App\Services\Settings\SettingsManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DomainSettingsController extends Controller
{
    public function __construct(private readonly SettingsManagementService $settings)
    {
    }

    public function index(Domain $domain): Response|\Illuminate\Http\RedirectResponse
    {
        if (! userCheckPermission('domain_setting_view') || ! $this->canAccessDomain($domain->domain_uuid)) {
            return redirect('/');
        }

        return Inertia::render('DomainSettings', [
            'domain' => [
                'domain_uuid' => $domain->domain_uuid,
                'domain_name' => $domain->domain_name,
                'domain_description' => $domain->domain_description,
            ],
            'routes' => [
                'current_page' => route('domains.settings.index', ['domain' => $domain]),
                'domains' => route('domains.index'),
                'default_settings' => route('default-settings.index'),
                'data_route' => route('domains.settings.data', ['domain' => $domain]),
                'store' => route('domains.settings.store', ['domain' => $domain]),
                'update' => route('domains.settings.update', ['domain' => $domain, 'setting' => '__SETTING__']),
                'item_options' => route('domains.settings.item.options', ['domain' => $domain]),
                'select_all' => route('domains.settings.select.all', ['domain' => $domain]),
                'bulk_revert' => route('domains.settings.bulk.revert', ['domain' => $domain]),
                'bulk_toggle' => route('domains.settings.bulk.toggle', ['domain' => $domain]),
                'copy' => route('domains.settings.copy', ['domain' => $domain]),
                'reload' => route('domains.settings.reload', ['domain' => $domain]),
            ],
            'permissions' => [
                'create' => userCheckPermission('domain_setting_add'),
                'update' => userCheckPermission('domain_setting_edit'),
                'destroy' => userCheckPermission('domain_setting_delete'),
                'copy' => userCheckPermission('domain_select') && userCheckPermission('domain_setting_add'),
                'copy_to_default' => userCheckPermission('default_setting_add') && userCheckPermission('default_setting_edit'),
                'category_edit' => userCheckPermission('domain_setting_category_edit'),
            ],
            'options' => [
                'categories' => $this->settings->categories($domain),
                'types' => SettingsManagementService::TYPE_OPTIONS,
                'domains' => $this->domainOptions($domain->domain_uuid),
            ],
        ]);
    }

    public function data(Request $request, Domain $domain): JsonResponse
    {
        if (! userCheckPermission('domain_setting_view') || ! $this->canAccessDomain($domain->domain_uuid)) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $perPage = min(max((int) $request->input('per_page', 50), 1), 5000);

        $paginator = $this->settings->effectiveDomainSettings(
            $domain,
            $request->input('filter', []),
            $request->input('sort'),
            (int) $request->input('page', 1),
            $perPage
        );

        return response()->json($paginator);
    }

    public function itemOptions(Request $request, Domain $domain): JsonResponse
    {
        if (! $this->canAccessDomain($domain->domain_uuid)) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $domainSettingUuid = $request->input('domain_setting_uuid');
        if ($domainSettingUuid && ! userCheckPermission('domain_setting_edit')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        if (! $domainSettingUuid && ! userCheckPermission('domain_setting_add')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        return response()->json([
            'item' => $this->settings->domainItem($domain, $request->only([
                'domain_setting_uuid',
                'default_setting_uuid',
                'default_value',
            ])),
            'types' => SettingsManagementService::TYPE_OPTIONS,
        ]);
    }

    public function store(SaveDomainSettingRequest $request, Domain $domain): JsonResponse
    {
        if (! userCheckPermission('domain_setting_add') || ! $this->canAccessDomain($domain->domain_uuid)) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $this->settings->saveDomainOverride($domain, $request->validated());

        return response()->json(['messages' => ['success' => ['Domain override created.']]], 201);
    }

    public function update(SaveDomainSettingRequest $request, Domain $domain, DomainSettings $setting): JsonResponse
    {
        if (! userCheckPermission('domain_setting_edit') || ! $this->canAccessDomain($domain->domain_uuid) || $setting->domain_uuid !== $domain->domain_uuid) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $this->settings->saveDomainOverride($domain, $request->validated(), $setting);

        return response()->json(['messages' => ['success' => ['Domain override updated.']]]);
    }

    public function selectAll(Request $request, Domain $domain): JsonResponse
    {
        if (! userCheckPermission('domain_setting_view') || ! $this->canAccessDomain($domain->domain_uuid)) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $items = collect($this->settings->effectiveDomainSettings($domain, $request->input('filter', []), $request->input('sort'), 1, 100000)->items())
            ->pluck('domain_setting_uuid')
            ->filter()
            ->values();

        return response()->json([
            'items' => $items,
            'messages' => ['success' => ['All matching overrides selected.']],
        ]);
    }

    public function bulkRevert(BulkSettingsActionRequest $request, Domain $domain): JsonResponse
    {
        if (! userCheckPermission('domain_setting_delete') || ! $this->canAccessDomain($domain->domain_uuid)) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $count = $this->settings->revertDomain($domain, $request->validated('items'));

        return response()->json(['messages' => ['success' => ["Reverted {$count} domain override(s)."]]]);
    }

    public function bulkToggle(BulkSettingsActionRequest $request, Domain $domain): JsonResponse
    {
        if (! userCheckPermission('domain_setting_edit') || ! $this->canAccessDomain($domain->domain_uuid)) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $count = $this->settings->toggleDomain($domain, $request->validated('items'));

        return response()->json(['messages' => ['success' => ["Toggled {$count} domain override(s)."]]]);
    }

    public function copy(BulkSettingsActionRequest $request, Domain $domain): JsonResponse
    {
        if (! userCheckPermission('domain_select') || ! userCheckPermission('domain_setting_add') || ! $this->canAccessDomain($domain->domain_uuid)) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $target = (string) $request->input('target_domain_uuid');
        if ($target !== 'default' && ! $this->canAccessDomain($target)) {
            return response()->json(['messages' => ['error' => ['Domain access denied.']]], 403);
        }

        if ($target === 'default' && (! userCheckPermission('default_setting_add') || ! userCheckPermission('default_setting_edit'))) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $count = $this->settings->copyDomainSettings($domain, $request->validated('items'), $target);

        return response()->json(['messages' => ['success' => ["Copied {$count} domain setting(s)."]]]);
    }

    public function reload(Domain $domain): JsonResponse
    {
        if (! userCheckPermission('default_setting_view') || ! $this->canAccessDomain($domain->domain_uuid)) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        try {
            $this->settings->reloadSessionSettings($domain);

            return response()->json(['messages' => ['success' => ['Settings reloaded.']]]);
        } catch (\Throwable $exception) {
            logger('DomainSettingsController@reload error: ' . $exception->getMessage() . ' at ' . $exception->getFile() . ':' . $exception->getLine());

            return response()->json([
                'messages' => ['error' => ['Unable to reload settings.']],
            ], 500);
        }
    }

    private function domainOptions(string $currentDomainUuid): array
    {
        $domains = collect(session('domains', []))
            ->map(fn ($domain) => [
                'value' => data_get($domain, 'domain_uuid'),
                'label' => $this->domainOptionLabel($domain),
            ])
            ->filter(fn ($domain) => $domain['value'] && $domain['label'] && $domain['value'] !== $currentDomainUuid)
            ->values();

        if (userCheckPermission('default_setting_add') && userCheckPermission('default_setting_edit')) {
            $domains->push(['value' => 'default', 'label' => 'Default Settings']);
        }

        return $domains->all();
    }

    private function domainOptionLabel(mixed $domain): string
    {
        $name = (string) data_get($domain, 'domain_name', '');
        $description = (string) data_get($domain, 'domain_description', '');

        return $description ?: $name;
    }

    private function canAccessDomain(string $domainUuid): bool
    {
        if (userCheckPermission('domain_all')) {
            return true;
        }

        if (session('domain_uuid') === $domainUuid) {
            return true;
        }

        return collect(session('domains', []))
            ->contains(fn ($domain) => data_get($domain, 'domain_uuid') === $domainUuid);
    }
}
