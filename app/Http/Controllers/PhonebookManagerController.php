<?php

namespace App\Http\Controllers;

use App\Models\Phonebook;
use App\Services\PhonebookService;
use App\Http\Requests\StorePhonebookRequest;
use App\Http\Requests\UpdatePhonebookRequest;
use App\Services\Provisioning\Phonebook\PhonebookBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PhonebookManagerController extends Controller
{
    public function index()
    {
        if (! userCheckPermission('phonebook_view')) {
            return redirect('/');
        }

        return Inertia::render('Phonebooks', [
            'pagination' => [
                'per_page' => fspbx_pagination_per_page(),
                'per_page_options' => fspbx_pagination_options(),
            ],
            'routes' => [
                'current_page' => route('phonebooks.index'),
                'data_route' => route('phonebooks.data'),
                'select_all' => route('phonebooks.select.all'),
                'bulk_delete' => route('phonebooks.bulk.delete'),
                'store' => route('phonebooks.store'),
                'item_options' => route('phonebooks.item.options'),
                'preview' => route('phonebooks.preview', ['phonebook' => '__PHONEBOOK_UUID__']),
            ],
            'permissions' => [
                'create' => userCheckPermission('phonebook_create'),
                'update' => userCheckPermission('phonebook_update'),
                'destroy' => userCheckPermission('phonebook_delete'),
            ],
        ]);
    }

    public function getData(Request $request)
    {
        if (! userCheckPermission('phonebook_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        return $this->scoped($request)
            ->select(['phonebook_uuid', 'domain_uuid', 'name', 'description', 'enabled', 'is_default', 'include_extensions', 'updated_at'])
            ->withCount(['contacts', 'devices'])
            ->allowedSorts(['name', 'enabled', 'is_default', 'updated_at'])
            ->defaultSort('name')
            ->paginate(fspbx_pagination_per_page($request));
    }

    public function getItemOptions(Request $request): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if ($itemUuid && ! userCheckPermission('phonebook_update')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        if (! $itemUuid && ! userCheckPermission('phonebook_create')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        if ($itemUuid) {
            $item = Phonebook::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->with('contacts')
                ->whereKey($itemUuid)
                ->firstOrFail();
        } else {
            $item = new Phonebook(['enabled' => true, 'is_default' => false, 'include_extensions' => true]);
            $item->setRelation('contacts', collect());
        }

        return response()->json([
            'item' => $item,
            'extensions_count' => \App\Models\Extensions::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->where('enabled', 'true')
                ->count(),
            'routes' => [
                'store_route' => route('phonebooks.store'),
                'update_route' => $itemUuid
                    ? route('phonebooks.update', ['phonebook' => $item->phonebook_uuid])
                    : null,
                'preview_route' => $itemUuid
                    ? route('phonebooks.preview', ['phonebook' => $item->phonebook_uuid])
                    : null,
            ],
        ]);
    }

    public function store(StorePhonebookRequest $request, PhonebookService $service): JsonResponse
    {
        try {
            $phonebook = $service->save($request->validated());

            return response()->json([
                'messages' => ['success' => ['Phonebook created successfully.']],
                'phonebook_uuid' => $phonebook->phonebook_uuid,
            ], 201);
        } catch (\Throwable $e) {
            logger('PhonebookManagerController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json(['messages' => ['error' => ['Failed to create phonebook.']]], 500);
        }
    }

    public function update(UpdatePhonebookRequest $request, Phonebook $phonebook, PhonebookService $service): JsonResponse
    {
        if ($phonebook->domain_uuid !== session('domain_uuid')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        try {
            $service->save($request->validated(), $phonebook);

            return response()->json(['messages' => ['success' => ['Phonebook updated successfully.']]]);
        } catch (\Throwable $e) {
            logger('PhonebookManagerController@update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json(['messages' => ['error' => ['Failed to update phonebook.']]], 500);
        }
    }

    public function selectAll(Request $request): JsonResponse
    {
        if (! userCheckPermission('phonebook_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $items = $this->scoped($request)
            ->select(['phonebook_uuid'])
            ->defaultSort('name')
            ->pluck('phonebook_uuid');

        return response()->json([
            'items' => $items,
            'messages' => ['success' => ['All matching phonebooks selected.']],
        ]);
    }

    public function bulkDelete(Request $request, PhonebookService $service): JsonResponse
    {
        if (! userCheckPermission('phonebook_delete')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $uuids = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['uuid'],
        ])['items'];

        $items = Phonebook::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('phonebook_uuid', $uuids)
            ->get();

        $deleted = $service->delete($items);

        return response()->json([
            'messages' => ['success' => ["Deleted {$deleted} phonebook(s)."]],
        ]);
    }

    /**
     * Preview the rendered directory entries for a phonebook (domain-scoped).
     */
    public function preview(Phonebook $phonebook, PhonebookBuilder $builder): JsonResponse
    {
        if (! userCheckPermission('phonebook_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        if ($phonebook->domain_uuid !== session('domain_uuid')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $entries = $builder->build($phonebook, (string) $phonebook->domain_uuid);

        return response()->json([
            'count' => count($entries),
            'entries' => array_slice($entries, 0, 100),
        ]);
    }

    private function scoped(Request $request): QueryBuilder
    {
        return QueryBuilder::for(Phonebook::class)
            ->where('domain_uuid', session('domain_uuid'))
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);
                    if ($needle === '') {
                        return;
                    }
                    $query->where(function ($query) use ($needle) {
                        $query->where('name', 'ilike', "%{$needle}%")
                            ->orWhere('description', 'ilike', "%{$needle}%");
                    });
                }),
            ]);
    }
}
