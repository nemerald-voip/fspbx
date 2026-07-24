<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmailTemplateRequest;
use App\Http\Requests\UpdateEmailTemplateRequest;
use App\Models\EmailTemplate;
use App\Services\EmailTemplateDefaultsInitializer;
use App\Services\EmailTemplatePreviewService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EmailTemplateController extends Controller
{
    public function index(EmailTemplateDefaultsInitializer $defaultsInitializer)
    {
        if (! userCheckPermission('email_templates_view')) {
            return redirect('/');
        }

        try {
            $defaultsInitializer->ensureSeeded();
        } catch (\Throwable $exception) {
            report($exception);
        }

        return Inertia::render('EmailTemplates', [
            'pagination' => [
                'per_page' => fspbx_pagination_per_page(),
                'per_page_options' => fspbx_pagination_options(),
            ],
            'routes' => [
                'data_route' => route('email-templates.data'),
                'item_options' => route('email-templates.item.options'),
                'store' => route('email-templates.store'),
                'select_all' => route('email-templates.select.all'),
                'bulk_delete' => route('email-templates.bulk.delete'),
                'bulk_toggle' => route('email-templates.bulk.toggle'),
                'copy' => route('email-templates.copy'),
            ],
            'permissions' => [
                'create' => userCheckPermission('email_templates_create'),
                'update' => userCheckPermission('email_templates_update'),
                'destroy' => userCheckPermission('email_templates_delete'),
                'manage_global' => userCheckPermission('email_templates_manage_global'),
            ],
            'options' => [
                'categories' => $this->distinctOptions('template_category'),
                'languages' => $this->distinctOptions('template_language'),
                'default_language' => get_domain_setting('language') ?: 'en-us',
            ],
        ]);
    }

    public function getData(Request $request)
    {
        if (! userCheckPermission('email_templates_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $paginator = $this->scoped($request)
            ->select([
                'email_template_uuid',
                'domain_uuid',
                'base_template_uuid',
                'base_version',
                'template_key',
                'template_type',
                'template_language',
                'template_category',
                'template_subcategory',
                'template_layout',
                'version',
                'template_subject',
                'template_enabled',
                'template_description',
                'updated_at',
            ])
            ->with('domain:domain_uuid,domain_name,domain_description')
            ->allowedSorts([
                'template_language',
                'template_category',
                'template_subcategory',
                'template_subject',
                'template_type',
                'template_enabled',
                'version',
                'updated_at',
            ])
            ->defaultSort('template_category', 'template_subcategory', 'template_language')
            ->paginate(fspbx_pagination_per_page($request))
            ->appends($request->query());

        return response()->json($paginator->through(fn (EmailTemplate $template) => [
            'email_template_uuid' => $template->email_template_uuid,
            'domain_uuid' => $template->domain_uuid,
            'domain_label' => $template->domain_uuid === null
                ? ($template->isDefault() ? 'All accounts' : 'Global override')
                : ($template->domain?->domain_description ?: $template->domain?->domain_name ?: 'Account'),
            'base_template_uuid' => $template->base_template_uuid,
            'base_version' => $template->base_version,
            'template_key' => $template->template_key,
            'template_type' => $template->template_type,
            'template_language' => $template->template_language,
            'template_category' => $template->template_category,
            'template_subcategory' => $template->template_subcategory,
            'template_layout' => $template->template_layout,
            'template_subject' => $template->template_subject,
            'version' => $template->version,
            'template_enabled' => $template->template_enabled,
            'template_description' => $template->template_description,
            'updated_at' => $template->updated_at,
            'locked' => $template->isDefault(),
            'manageable' => $this->canManage($template),
        ]));
    }

    public function getItemOptions(Request $request): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if ($itemUuid && ! userCheckPermission('email_templates_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        if (! $itemUuid && ! userCheckPermission('email_templates_create')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $item = $itemUuid
            ? $this->visibleQuery()->whereKey($itemUuid)->firstOrFail()
            : new EmailTemplate([
                'domain_uuid' => session('domain_uuid'),
                'template_language' => 'en-us',
                'template_layout' => 'standard',
                'template_type' => 'custom',
                'template_enabled' => true,
            ]);

        $locked = $item->exists && (
            $item->isDefault()
            || ! userCheckPermission('email_templates_update')
            || ! $this->canManage($item)
        );

        return response()->json([
            'item' => $item,
            'locked' => $locked,
            'categories' => $this->distinctOptions('template_category'),
            'languages' => $this->supportedLanguageOptions(),
            'domains' => $this->domainOptions($item),
            'defaults' => EmailTemplate::query()
                ->where('template_type', 'default')
                ->orderBy('template_category')
                ->orderBy('template_subcategory')
                ->get()
                ->map(function (EmailTemplate $template) use ($itemUuid) {
                    $option = [
                        'value' => $template->email_template_uuid,
                        'label' => Str::headline($template->template_category).' / '.Str::headline($template->template_subcategory),
                        'category' => $template->template_category,
                        'subcategory' => $template->template_subcategory,
                        'language' => $template->template_language,
                        'version' => $template->version,
                    ];

                    // Only the create form needs the base content to pre-fill the editor.
                    if (! $itemUuid) {
                        $option['layout'] = $template->template_layout ?: 'standard';
                        $option['subject'] = $template->template_subject;
                        $option['html'] = $template->template_html;
                        $option['text'] = $template->template_text;
                    }

                    return $option;
                }),
            'routes' => [
                'store_route' => route('email-templates.store'),
                'preview_route' => route('email-templates.preview'),
                'update_route' => $itemUuid
                    && $item->isCustom()
                    && userCheckPermission('email_templates_update')
                    && $this->canManage($item)
                    ? route('email-templates.update', ['email_template' => $item->email_template_uuid])
                    : null,
            ],
        ]);
    }

    public function preview(Request $request, EmailTemplatePreviewService $previewer): JsonResponse
    {
        if (! userCheckPermission('email_templates_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $data = $request->validate([
            'email_template_uuid' => ['nullable', 'uuid'],
            'template_category' => ['required', 'string', 'max:255'],
            'template_subcategory' => ['required', 'string', 'max:255'],
            'template_layout' => ['required', 'string', 'in:standard,none'],
            'template_subject' => ['required', 'string', 'max:500'],
            'template_html' => ['required', 'string'],
            'template_text' => ['required', 'string'],
        ]);

        $template = filled($data['email_template_uuid'] ?? null)
            ? $this->visibleQuery()->whereKey($data['email_template_uuid'])->firstOrFail()
            : null;

        $canPreviewDraft = $template
            ? $template->isCustom()
                && userCheckPermission('email_templates_update')
                && $this->canManage($template)
            : userCheckPermission('email_templates_create');

        abort_unless($template || $canPreviewDraft, 403);

        $definition = $canPreviewDraft ? $data : [
            'template_category' => $template->template_category,
            'template_subcategory' => $template->template_subcategory,
            'template_layout' => $template->template_layout,
            'template_subject' => $template->template_subject,
            'template_html' => $template->template_html,
            'template_text' => $template->template_text,
        ];

        try {
            return response()->json($previewer->render($definition));
        } catch (\Throwable $exception) {
            return response()->json([
                'errors' => [
                    'preview' => ['Preview could not be rendered: '.$exception->getMessage()],
                ],
            ], 422);
        }
    }

    public function store(StoreEmailTemplateRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['template_key'] = $this->templateKey($data);
        $data['template_type'] = 'custom';
        $data['template_layout'] = 'standard';
        $data['version'] = null;

        if ($baseUuid = $data['base_template_uuid'] ?? null) {
            $base = EmailTemplate::query()
                ->whereKey($baseUuid)
                ->where('template_type', 'default')
                ->firstOrFail();
            $data['base_version'] = $base->version;
            $data['template_layout'] = $base->template_layout;
        }

        $template = EmailTemplate::create($data);

        return response()->json([
            'messages' => ['success' => ['Custom email template created.']],
            'email_template_uuid' => $template->email_template_uuid,
            'routes' => [
                'update_route' => route('email-templates.update', ['email_template' => $template->email_template_uuid]),
            ],
        ], 201);
    }

    public function update(UpdateEmailTemplateRequest $request, EmailTemplate $email_template): JsonResponse
    {
        abort_if($email_template->isDefault(), 403, 'Default templates are managed by FS PBX updates.');
        abort_unless($this->canManage($email_template), 403);

        $data = $request->validated();
        $data['template_key'] = $this->templateKey($data);
        $data['template_type'] = 'custom';
        $data['template_layout'] = $email_template->template_layout ?: 'standard';
        $data['version'] = null;

        if ($baseUuid = $data['base_template_uuid'] ?? null) {
            $base = EmailTemplate::query()
                ->whereKey($baseUuid)
                ->where('template_type', 'default')
                ->firstOrFail();
            $data['base_version'] = $base->version;
            $data['template_layout'] = $base->template_layout;
        } else {
            $data['base_version'] = null;
        }

        $email_template->update($data);

        return response()->json([
            'messages' => ['success' => ['Custom email template updated.']],
        ]);
    }

    public function selectAll(Request $request): JsonResponse
    {
        if (! userCheckPermission('email_templates_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        return response()->json([
            'items' => $this->scoped($request)
                ->defaultSort('template_category', 'template_subcategory', 'template_language')
                ->pluck('email_template_uuid'),
            'messages' => ['success' => ['All matching email templates selected.']],
        ]);
    }

    public function copy(Request $request): JsonResponse
    {
        if (! userCheckPermission('email_templates_create')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $items = $this->validatedItems($request);
        $domainUuid = $this->copyTargetDomain($request);
        $templates = EmailTemplate::query()
            ->whereIn('email_template_uuid', $items)
            ->where('template_type', 'default')
            ->get();
        $created = 0;

        DB::transaction(function () use ($templates, $domainUuid, &$created) {
            foreach ($templates as $template) {
                $exists = EmailTemplate::query()
                    ->where('template_type', 'custom')
                    ->where('template_key', $template->template_key)
                    ->where('template_language', $template->template_language)
                    ->where(function ($query) use ($domainUuid) {
                        $domainUuid
                            ? $query->where('domain_uuid', $domainUuid)
                            : $query->whereNull('domain_uuid');
                    })
                    ->exists();

                if ($exists) {
                    continue;
                }

                EmailTemplate::query()->create([
                    'domain_uuid' => $domainUuid,
                    'base_template_uuid' => $template->email_template_uuid,
                    'base_version' => $template->version,
                    'template_key' => $template->template_key,
                    'template_type' => 'custom',
                    'template_language' => $template->template_language,
                    'template_category' => $template->template_category,
                    'template_subcategory' => $template->template_subcategory,
                    'template_layout' => $template->template_layout,
                    'version' => null,
                    'template_subject' => $template->template_subject,
                    'template_html' => $template->template_html,
                    'template_text' => $template->template_text,
                    'template_enabled' => true,
                    'template_description' => $template->template_description,
                ]);
                $created++;
            }
        });

        return response()->json([
            'messages' => ['success' => [$created > 0
                ? "Created {$created} custom email template(s)."
                : 'Matching custom templates already exist for this account.']],
        ]);
    }

    public function bulkToggle(Request $request): JsonResponse
    {
        if (! userCheckPermission('email_templates_update')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $items = $this->validatedItems($request);
        $templates = $this->manageableCustomQuery()->whereIn('email_template_uuid', $items)->get();

        DB::transaction(function () use ($templates) {
            foreach ($templates as $template) {
                $template->template_enabled = ! $template->template_enabled;
                $template->save();
            }
        });

        return response()->json([
            'messages' => ['success' => ["Updated {$templates->count()} custom email template(s)."]],
        ]);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        if (! userCheckPermission('email_templates_delete')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $items = $this->validatedItems($request);
        $deleted = $this->manageableCustomQuery()->whereIn('email_template_uuid', $items)->delete();

        return response()->json([
            'messages' => ['success' => ["Deleted {$deleted} custom email template(s)."]],
        ]);
    }

    private function scoped(Request $request): QueryBuilder
    {
        return QueryBuilder::for($this->visibleQuery())
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);
                    if ($needle === '') {
                        return;
                    }

                    $query->where(function ($query) use ($needle) {
                        $query->where('template_subject', 'ilike', "%{$needle}%")
                            ->orWhere('template_category', 'ilike', "%{$needle}%")
                            ->orWhere('template_subcategory', 'ilike', "%{$needle}%")
                            ->orWhere('template_description', 'ilike', "%{$needle}%")
                            ->orWhere('template_language', 'ilike', "%{$needle}%");
                    });
                }),
                AllowedFilter::exact('category', 'template_category'),
                AllowedFilter::exact('type', 'template_type'),
                AllowedFilter::exact('language', 'template_language'),
            ]);
    }

    private function visibleQuery(): Builder
    {
        return EmailTemplate::query()
            ->where(function (Builder $query) {
                $query
                    ->where(function (Builder $defaults) {
                        $defaults->where('template_type', 'default')
                            ->whereNull('domain_uuid');
                    })
                    ->orWhere(function (Builder $globalCustom) {
                        $globalCustom->where('template_type', 'custom')
                            ->whereNull('domain_uuid');
                    })
                    ->orWhere(function (Builder $accountCustom) {
                        $accountCustom->where('template_type', 'custom')
                            ->where('domain_uuid', session('domain_uuid'));
                    });
            });
    }

    private function canManage(EmailTemplate $template): bool
    {
        if (! $template->isCustom()) {
            return false;
        }

        if ($template->domain_uuid === session('domain_uuid')) {
            return true;
        }

        return $template->domain_uuid === null
            && userCheckPermission('email_templates_manage_global');
    }

    private function manageableCustomQuery(): Builder
    {
        return EmailTemplate::query()
            ->where('template_type', 'custom')
            ->where(function (Builder $query) {
                $query->where('domain_uuid', session('domain_uuid'));

                if (userCheckPermission('email_templates_manage_global')) {
                    $query->orWhereNull('domain_uuid');
                }
            });
    }

    private function copyTargetDomain(Request $request): ?string
    {
        if (! $request->has('domain_uuid')) {
            return session('domain_uuid');
        }

        $domainUuid = $request->input('domain_uuid');
        if ($domainUuid === '__global__' || blank($domainUuid)) {
            abort_unless(userCheckPermission('email_templates_manage_global'), 403);

            return null;
        }

        validator(['domain_uuid' => $domainUuid], [
            'domain_uuid' => ['required', 'uuid', 'exists:v_domains,domain_uuid'],
        ])->validate();

        abort_unless($domainUuid === session('domain_uuid'), 403);

        return $domainUuid;
    }

    private function validatedItems(Request $request): array
    {
        return $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'uuid'],
        ])['items'];
    }

    private function distinctOptions(string $column): array
    {
        return $this->visibleQuery()
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->map(fn ($value) => ['value' => $value, 'label' => $value])
            ->values()
            ->all();
    }

    /**
     * Languages a custom template can be created in: the locales FS PBX
     * supports (v_menu_languages), plus any already used by templates,
     * with the account default guaranteed to be present.
     */
    private function supportedLanguageOptions(): array
    {
        $fromTemplates = EmailTemplate::query()
            ->whereNotNull('template_language')
            ->where('template_language', '<>', '')
            ->distinct()
            ->pluck('template_language');

        $fromMenu = Schema::hasTable('v_menu_languages')
            ? DB::table('v_menu_languages')->whereNotNull('menu_language')->distinct()->pluck('menu_language')
            : collect();

        return $fromTemplates
            ->merge($fromMenu)
            ->push(get_domain_setting('language') ?: 'en-us')
            ->map(fn ($code) => strtolower(trim((string) $code)))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->map(fn ($code) => ['value' => $code, 'label' => $code])
            ->all();
    }

    private function templateKey(array $data): string
    {
        return strtolower(trim($data['template_category']).'.'.trim($data['template_subcategory']));
    }

    private function domainOptions(EmailTemplate $item): array
    {
        $domainUuid = session('domain_uuid');
        $domain = collect(session('domains', []))
            ->first(fn ($domain) => data_get($domain, 'domain_uuid') === $domainUuid);
        $domainLabel = data_get($domain, 'domain_description')
            ?: data_get($domain, 'domain_name')
            ?: session('domain_name')
            ?: 'Current account';

        $options = collect([
            ['value' => $domainUuid, 'label' => $domainLabel],
        ])->filter(fn ($option) => filled($option['value']));

        if (
            userCheckPermission('email_templates_manage_global')
            || ($item->exists && $item->isCustom() && $item->domain_uuid === null)
        ) {
            $options->push(['value' => '__global__', 'label' => 'Global override']);
        }

        if ($item->exists && $item->isDefault()) {
            $options->push(['value' => '__default__', 'label' => 'Default for all accounts']);
        }

        return $options->values()->all();
    }
}
