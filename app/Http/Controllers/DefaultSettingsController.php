<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\BulkSettingsActionRequest;
use App\Http\Requests\Settings\SaveDefaultSettingRequest;
use App\Models\DefaultSettings;
use App\Models\Domain;
use App\Services\Settings\SettingsManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DefaultSettingsController extends Controller
{
    public function __construct(private readonly SettingsManagementService $settings)
    {
    }

    public function index(): Response|\Illuminate\Http\RedirectResponse
    {
        if (! userCheckPermission('default_setting_view')) {
            return redirect('/');
        }

        $currentDomainUuid = session('domain_uuid');

        return Inertia::render('DefaultSettings', [
            'routes' => [
                'current_page' => route('default-settings.index'),
                'current_domain_settings' => $currentDomainUuid
                    ? route('domains.settings.index', ['domain' => $currentDomainUuid])
                    : null,
                'data_route' => route('default-settings.data'),
                'store' => route('default-settings.store'),
                'update' => route('default-settings.update', ['default_setting' => '__SETTING__']),
                'destroy' => route('default-settings.destroy', ['default_setting' => '__SETTING__']),
                'item_options' => route('default-settings.item.options'),
                'select_all' => route('default-settings.select.all'),
                'bulk_delete' => route('default-settings.bulk.delete'),
                'bulk_toggle' => route('default-settings.bulk.toggle'),
                'copy_to_domain' => route('default-settings.copy-to-domain'),
                'reload' => route('default-settings.reload'),
                'affected_domains' => route('default-settings.affected-domains', ['default_setting' => '__SETTING__']),
                'domain_settings' => route('domains.settings.index', ['domain' => '__DOMAIN__']),
            ],
            'permissions' => [
                'create' => userCheckPermission('default_setting_add'),
                'update' => userCheckPermission('default_setting_edit'),
                'destroy' => userCheckPermission('default_setting_delete'),
                'copy_to_domain' => userCheckPermission('domain_select') && userCheckPermission('domain_setting_add'),
                'domain_settings' => userCheckPermission('domain_setting_view') && (bool) $currentDomainUuid,
            ],
            'options' => [
                'categories' => $this->settings->categories(),
                'types' => SettingsManagementService::typeLabels(),
                'domains' => $this->domainOptions(),
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (! userCheckPermission('default_setting_view')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        $perPage = min(max((int) $request->input('per_page', 50), 1), 5000);

        $paginator = $this->settings->defaultSettings(
            $request->input('filter', []),
            $request->input('sort'),
            (int) $request->input('page', 1),
            $perPage
        );

        return response()->json($paginator);
    }

    public function itemOptions(Request $request): JsonResponse
    {
        $uuid = $request->input('itemUuid');

        if ($uuid && ! userCheckPermission('default_setting_edit')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        if (! $uuid && ! userCheckPermission('default_setting_add')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        return response()->json([
            'item' => $this->settings->defaultItem($uuid),
            'types' => SettingsManagementService::typeLabels(),
        ]);
    }

    public function store(SaveDefaultSettingRequest $request): JsonResponse
    {
        if (! userCheckPermission('default_setting_add')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        $this->settings->saveDefault($request->validated());

        return response()->json(['messages' => ['success' => [__('Default setting created.')]]], 201);
    }

    public function update(SaveDefaultSettingRequest $request, DefaultSettings $defaultSetting): JsonResponse
    {
        if (! userCheckPermission('default_setting_edit')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        $this->settings->saveDefault($request->validated(), $defaultSetting);

        return response()->json(['messages' => ['success' => [__('Default setting updated.')]]]);
    }

    public function destroy(DefaultSettings $defaultSetting): JsonResponse
    {
        if (! userCheckPermission('default_setting_delete')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        $this->settings->deleteDefaults([$defaultSetting->default_setting_uuid]);

        return response()->json(['messages' => ['success' => [__('Default setting deleted.')]]]);
    }

    public function selectAll(Request $request): JsonResponse
    {
        if (! userCheckPermission('default_setting_view')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        $items = collect($this->settings->defaultSettings($request->input('filter', []), $request->input('sort'), 1, 100000)->items())
            ->pluck('default_setting_uuid')
            ->values();

        return response()->json([
            'items' => $items,
            'messages' => ['success' => [__('All matching default settings selected.')]],
        ]);
    }

    public function bulkToggle(BulkSettingsActionRequest $request): JsonResponse
    {
        if (! userCheckPermission('default_setting_edit')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        $count = $this->settings->toggleDefault($request->validated('items'));

        return response()->json(['messages' => ['success' => [trans_choice('{1} Toggled :count default setting.|[0,*] Toggled :count default settings.', $count)]]]);
    }

    public function bulkDelete(BulkSettingsActionRequest $request): JsonResponse
    {
        if (! userCheckPermission('default_setting_delete')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        $count = $this->settings->deleteDefaults($request->validated('items'));

        return response()->json(['messages' => ['success' => [trans_choice('{1} Deleted :count default setting.|[0,*] Deleted :count default settings.', $count)]]]);
    }

    public function copyToDomain(BulkSettingsActionRequest $request): JsonResponse
    {
        if (! userCheckPermission('domain_select') || ! userCheckPermission('domain_setting_add')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        $targetDomainUuid = (string) $request->input('target_domain_uuid');
        if (! $this->canAccessDomain($targetDomainUuid)) {
            return response()->json(['messages' => ['error' => [__('Domain access denied.')]]], 403);
        }

        $targetDomain = Domain::query()->findOrFail($targetDomainUuid);
        $count = $this->settings->copyDefaultsToDomain($request->validated('items'), $targetDomain);
        $targetDomainLabel = $targetDomain->domain_description ?: $targetDomain->domain_name;

        return response()->json(['messages' => ['success' => [trans_choice('{1} Copied :count setting to :domain.|[0,*] Copied :count settings to :domain.', $count, ['domain' => $targetDomainLabel])]]]);
    }

    public function affectedDomains(DefaultSettings $defaultSetting): JsonResponse
    {
        if (! userCheckPermission('default_setting_view')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        return response()->json(['domains' => $this->settings->affectedDomains($defaultSetting)]);
    }

    public function reload(): JsonResponse
    {
        if (! userCheckPermission('default_setting_view')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        try {
            $this->settings->reloadSessionSettings();

            return response()->json(['messages' => ['success' => [__('Settings reloaded.')]]]);
        } catch (\Throwable $exception) {
            logger('DefaultSettingsController@reload error: ' . $exception->getMessage() . ' at ' . $exception->getFile() . ':' . $exception->getLine());

            return response()->json([
                'messages' => ['error' => [__('Unable to reload settings.')]],
            ], 500);
        }
    }

    private function domainOptions(): array
    {
        return collect(session('domains', []))
            ->map(fn ($domain) => [
                'value' => data_get($domain, 'domain_uuid'),
                'label' => $this->domainOptionLabel($domain),
            ])
            ->filter(fn ($domain) => $domain['value'] && $domain['label'])
            ->values()
            ->all();
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
