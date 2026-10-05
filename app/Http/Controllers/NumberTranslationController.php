<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveNumberTranslationRequest;
use App\Models\NumberTranslation;
use App\Services\NumberTranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class NumberTranslationController extends Controller
{
    public function __construct(private readonly NumberTranslationService $translations)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless(userCheckPermission('number_translation_view'), 403);

        return response()->json(QueryBuilder::for(NumberTranslation::class)
            ->allowedFilters([AllowedFilter::callback('search', function ($query, $value) {
                $value = is_array($value) ? implode(',', $value) : $value;
                $query->where(function ($query) use ($value) {
                    $query->where('number_translation_name', 'ilike', '%' . $value . '%')
                        ->orWhere('number_translation_description', 'ilike', '%' . $value . '%');
                });
            })])
            ->allowedSorts(['number_translation_name', 'number_translation_enabled'])
            ->defaultSort('number_translation_name')->withCount('rules')
            ->paginate(min(max((int) $request->input('per_page', 25), 1), 100))
            ->through(fn ($profile) => [
                'uuid' => $profile->getKey(),
                'name' => $profile->number_translation_name,
                'description' => $profile->number_translation_description,
                'enabled' => $profile->number_translation_enabled === 'true',
                'rules_count' => $profile->rules_count,
            ]));
    }

    public function show(NumberTranslation $numberTranslation): JsonResponse
    {
        abort_unless(userCheckPermission('number_translation_view'), 403);
        return response()->json($this->translations->item($numberTranslation));
    }

    public function store(SaveNumberTranslationRequest $request): JsonResponse
    {
        $profile = $this->translations->save($request->validated());
        return $this->mutationResponse(__('Number translation created.'), $profile, 201);
    }

    public function update(SaveNumberTranslationRequest $request, NumberTranslation $numberTranslation): JsonResponse
    {
        $profile = $this->translations->save($request->validated(), $numberTranslation);
        return $this->mutationResponse(__('Number translation updated.'), $profile);
    }

    public function destroy(NumberTranslation $numberTranslation): JsonResponse
    {
        abort_unless(userCheckPermission('number_translation_delete'), 403);
        $this->translations->delete($numberTranslation);
        return $this->mutationResponse(__('Number translation deleted.'));
    }

    public function sync(): JsonResponse
    {
        abort_unless(userCheckPermission('number_translation_edit'), 403);
        return $this->mutationResponse(__('Number translations synchronized.'));
    }

    private function mutationResponse(string $message, ?NumberTranslation $profile = null, int $status = 200): JsonResponse
    {
        $runtime = $this->translations->synchronize();
        return response()->json([
            'item' => $profile ? $this->translations->item($profile) : null,
            'runtime_synchronized' => $runtime['synchronized'],
            'messages' => $runtime['synchronized'] ? ['success' => [$message]] : ['error' => [
                __('The changes are saved, but FreeSWITCH could not apply them. Correct the problem and use Sync to retry.') . ' ' . $runtime['error'],
            ]],
        ], $status);
    }
}
