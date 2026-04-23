<?php

namespace App\Http\Controllers;

use App\Models\Recordings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\OpenAIService;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class RecordingsManagerController extends Controller
{
    protected string $viewName = 'RecordingsManager';

    public function index()
    {
        if (! userCheckPermission('recording_view')) {
            return redirect('/');
        }

        $openAiService = app(OpenAIService::class);

        return Inertia::render($this->viewName, [
            'routes' => [
                'current_page' => route('recordings-manager.index'),
                'data_route' => route('recordings-manager.data'),
                'store' => route('recordings.store'),
                'show' => route('recordings.show', ['recording' => '__RECORDING__']),
                'update' => route('recordings.update', ['recording' => '__RECORDING__']),
                'destroy' => route('recordings.destroy', ['recording' => '__RECORDING__']),
                'bulk_delete' => route('recordings-manager.bulk.delete'),
                'select_all' => route('recordings-manager.select.all'),
            ],
            'permissions' => $this->getRecordingPermissions(),
            'recording_options' => [
                'routes' => [
                    'text_to_speech_route' => route('greetings.textToSpeech'),
                    'upload_greeting_route' => route('greetings.file.upload'),
                ],
                'voices' => $openAiService->getVoices(),
                'default_voice' => $openAiService->getDefaultVoice(),
                'speeds' => $openAiService->getSpeeds(),
                'phone_call_instructions' => [
                    'Dial <strong>*732</strong> from your phone.',
                    'Follow the prompts to record a new message.',
                    'Refresh the list after saving if you do not see it immediately.',
                ],
                'sample_message' => 'Thank you for calling. Please listen carefully to the following message.',
            ],
        ]);
    }

    public function getData(Request $request)
    {
        if (! userCheckPermission('recording_view')) {
            return response()->json([
                'errors' => ['auth' => ['Access denied.']],
            ], 403);
        }

        $paginator = $this->buildQuery()
            ->defaultSort('-insert_date')
            ->paginate(50)
            ->appends($request->query());

        $paginator->setCollection(
            $paginator->getCollection()->map(function (Recordings $recording) {
                return $this->serializeRecording($recording);
            })
        );

        return response()->json($paginator);
    }

    public function selectAll()
    {
        if (! userCheckPermission('recording_view')) {
            return response()->json([
                'errors' => ['auth' => ['Access denied.']],
            ], 403);
        }

        return response()->json([
            'items' => $this->buildQuery()
                ->defaultSort('-insert_date')
                ->get()
                ->pluck('recording_uuid'),
        ]);
    }

    public function bulkDelete(Request $request)
    {
        if (! userCheckPermission('recording_delete')) {
            return response()->json([
                'errors' => ['auth' => ['Access denied.']],
            ], 403);
        }

        $items = collect($request->input('items', []))
            ->filter()
            ->values();

        if ($items->isEmpty()) {
            return response()->json([
                'messages' => ['success' => ['No recordings were selected.']],
            ]);
        }

        $recordings = Recordings::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('recording_uuid', $items)
            ->get();

        foreach ($recordings as $recording) {
            $storagePath = session('domain_name').'/'.$recording->recording_filename;
            $recording->delete();
            Storage::disk('recordings')->delete($storagePath);
        }

        return response()->json([
            'messages' => ['success' => ['Selected recording(s) were deleted successfully.']],
        ]);
    }

    public function download(Recordings $recording)
    {
        if (! userCheckPermission('recording_download')) {
            abort(403);
        }

        if ($recording->domain_uuid !== session('domain_uuid')) {
            abort(404);
        }

        $storagePath = session('domain_name').'/'.$recording->recording_filename;

        if (! Storage::disk('recordings')->exists($storagePath)) {
            abort(404);
        }

        return response()->download(
            Storage::disk('recordings')->path($storagePath),
            basename($recording->recording_filename),
            [
                'Content-Type' => Storage::disk('recordings')->mimeType($storagePath) ?: 'application/octet-stream',
            ]
        );
    }

    protected function buildQuery(): QueryBuilder
    {
        return QueryBuilder::for(Recordings::class)
            ->where('domain_uuid', session('domain_uuid'))
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);

                    if ($needle === '') {
                        return;
                    }

                    $query->where(function ($nested) use ($needle) {
                        $nested->where('recording_name', 'ilike', "%{$needle}%")
                            ->orWhere('recording_filename', 'ilike', "%{$needle}%")
                            ->orWhere('recording_description', 'ilike', "%{$needle}%");
                    });
                }),
            ])
            ->allowedSorts([
                'recording_name',
                'recording_filename',
                'recording_description',
                'insert_date',
            ]);
    }

    protected function serializeRecording(Recordings $recording): array
    {
        $storagePath = session('domain_name').'/'.$recording->recording_filename;
        $fileExists = Storage::disk('recordings')->exists($storagePath);
        $playUrl = $fileExists
            ? route('recordings.file', ['filename' => $recording->recording_filename])
            : null;

        return [
            'recording_uuid' => (string) $recording->recording_uuid,
            'recording_name' => $recording->recording_name,
            'recording_filename' => $recording->recording_filename,
            'recording_description' => $recording->recording_description,
            'insert_date' => $recording->insert_date,
            'file_exists' => $fileExists,
            'play_url' => $playUrl,
            'download_url' => $fileExists
                ? route('recordings-manager.download', ['recording' => $recording])
                : null,
        ];
    }

    protected function getRecordingPermissions(): array
    {
        return [
            'recording_create' => userCheckPermission('recording_add') || userCheckPermission('recording_upload'),
            'recording_update' => userCheckPermission('recording_edit'),
            'recording_destroy' => userCheckPermission('recording_delete'),
            'recording_play' => userCheckPermission('recording_play'),
            'recording_download' => userCheckPermission('recording_download'),
        ];
    }
}
