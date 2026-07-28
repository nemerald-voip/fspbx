<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecordingBlobRequest;
use App\Http\Requests\StoreRecordingRequest;
use App\Http\Requests\UpdateRecordingRequest;
use App\Models\CallCenterQueues;
use App\Models\Domain;
use App\Models\Recordings;
use App\Models\RingGroups;
use App\Services\RecordingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class RecordingsController extends Controller
{
    public function __construct(private RecordingService $recordings) {}

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $output = [];
        $recordingsCollection = $this->recordings->list(
            (string) Session::get('domain_uuid'),
            null,
            null,
            'insert_date'
        );
        if ($recordingsCollection) {
            foreach ($recordingsCollection as $recording) {
                $path = Session::get('domain_name').'/'.$recording->recording_filename;
                if (Storage::disk('recordings')->exists($path)) {
                    $output[] = [
                        'id' => $recording->recording_uuid,
                        'filename' => $recording->recording_filename,
                        'name' => $recording->recording_name,
                        'description' => (string) $recording->recording_description,
                    ];
                }
            }
        }

        return response()->json(['collection' => $output]);
    }

    /**
     * Store a new recording.
     *
     * @param StoreRecordingRequest $request The request object.
     * @return \Illuminate\Http\JsonResponse The JSON response.
     */
    public function store(StoreRecordingRequest $request)
    {
        $attributes = $request->validated();
        $domain = Domain::query()->whereKey(Session::get('domain_uuid'))->firstOrFail();
        $audio = $request->file('greeting_filename');
        $temporaryRecording = null;

        if (! $audio && $request->filled('greeting_recorded_file') && Storage::exists($request->greeting_recorded_file)) {
            $temporaryRecording = $request->greeting_recorded_file;
            $audio = new UploadedFile(
                Storage::path($temporaryRecording),
                basename($temporaryRecording),
                null,
                null,
                true
            );
        }

        if (! $audio) {
            return response()->json(['error' => 422, 'message' => 'Failed to upload file'], 422);
        }

        try {
            $result = $this->recordings->create(
                $domain,
                $attributes['greeting_name'],
                $attributes['greeting_description'],
                $audio,
                (string) Session::get('user_uuid')
            );
            $recording = $result['recording'];
        } finally {
            if ($temporaryRecording) {
                Storage::delete($temporaryRecording);
            }
        }

        // Return the JSON response
        return response()->json([
            'status' => "success",
            'id' => $recording->recording_uuid,
            'name' => $recording->recording_name,
            'filename' => $recording->recording_filename,
            'message' => 'Greeting created successfully'
        ]);
    }

    public function storeBlob(StoreRecordingBlobRequest $request)
    {
        try {
            $request->validated();
            $blobInput = $request->file('recorded_file');
            $filename = 'input_'.Session::get('domain_name').'_'.uniqid();
            $encodedFilename = 'recorded_'.Session::get('domain_name').'_'.uniqid().'.wav';
            if (Storage::put($filename, file_get_contents($blobInput))) {
                $inputPath = Storage::path($filename);
                $outputPath = Storage::path($encodedFilename);
                shell_exec('ffmpeg -i '.$inputPath.' -acodec pcm_s16le -ac 1 -ar 16000 '.$outputPath);
                if (!Storage::exists($encodedFilename) || !Storage::size($encodedFilename)) {
                    throw new \Exception('Failed to encode audio');
                }
                return response()->json([
                    'status' => "success",
                    'tempfile' => $encodedFilename
                ]);
            } else {
                throw new \Exception("Failed to upload file");
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => 500,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Show the specified resource in storage.
     *
     * @param  Recordings $recording
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function show(Recordings $recording)
    {
        $recording = $this->recordings->find(
            (string) Session::get('domain_uuid'),
            (string) $recording->recording_uuid
        );
        abort_unless($recording, 404);

        return response()->json([
            'id' => $recording->recording_uuid,
            'filename' => $recording->recording_filename,
            'name' => $recording->recording_name,
            'description' => (string) $recording->recording_description,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  UpdateRecordingRequest $request
     * @param  Recordings $recording
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function update(UpdateRecordingRequest $request, Recordings $recording)
    {
        $attributes = $request->validated();
        $domain = Domain::query()->whereKey(Session::get('domain_uuid'))->firstOrFail();
        $recording = $this->recordings->find((string) $domain->domain_uuid, (string) $recording->recording_uuid);
        abort_unless($recording, 404);

        $result = $this->recordings->update($recording, $domain, [
            'recording_name' => $attributes['greeting_name'],
            'recording_description' => $attributes['greeting_description'],
        ], null, (string) Session::get('user_uuid'));
        $recording = $result['recording'];

        return response()->json([
            'status' => "success",
            'id' => $recording->recording_uuid,
            'filename' => $recording->recording_filename,
            'message' => 'Recording has been saved'
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Recordings  $recording
     * @param $entity
     * @param $entityid
     * @return \Illuminate\Http\JsonResponse
     */
    public function use(Recordings $recording, $entity, $entityid)
    {
        switch ($entity) {
            case 'ringGroup';
                /** @var RingGroups $entity */
                $entity = RingGroups::findOrFail($entityid);
                $entity->ring_group_greeting = $recording->recording_filename;
                $entity->save();
                break;
            case 'contactCenter';
                /** @var CallCenterQueues $entity */
                $entity = CallCenterQueues::findOrFail($entityid);
                $entity->queue_greeting = $recording->recording_filename;
                $entity->save();
                break;
            default:
                return response()->json([
                    'error' => 401,
                  'message' => 'Invalid entity'
                ]);
        }

        return response()->json([
            'status' => "success",
            'id' => $recording->recording_uuid,
            'filename' => $recording->recording_filename,
            'message' => 'Recording has been set'
        ]);
    }

    /**
     * Get recordings greeting.
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function file(string $filename)
    {
        $path = Session::get('domain_name').'/'.$filename;
        if (!Storage::disk('recordings')->exists($path)) {
            abort(404);
        }
        $file = Storage::disk('recordings')->path($path);
        $type = Storage::disk('recordings')->mimeType($path);
        $response = Response::make(file_get_contents($file));
        $response->header("Content-Type", $type);
        $response->header("Accept-Ranges", "bytes");
        $response->header("Content-Length", Storage::disk('recordings')->size($path));
        return $response;
    }

    public function destroy(Recordings $recording)
    {
        $domain = Domain::query()->whereKey(Session::get('domain_uuid'))->firstOrFail();
        $recording = $this->recordings->find((string) $domain->domain_uuid, (string) $recording->recording_uuid);
        abort_unless($recording, 404);
        $deleted = $this->recordings->delete($recording, $domain);
        if ($deleted) {
            return response()->json([
                'status' => 'success',
                'id' => $recording->recording_uuid,
                'filename' => $recording->recording_filename,
                'message' => 'Recording have been deleted'
            ]);
        } else {
            return response()->json([
                'error' => 401,
                'message' => 'There was an error deleting this Recording'
            ]);
        }
    }
}
