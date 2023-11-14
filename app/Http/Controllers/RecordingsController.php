<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecordingBlobRequest;
use App\Http\Requests\StoreRecordingRequest;
use App\Http\Requests\UpdateRecordingRequest;
use App\Models\CallCenterQueues;
use App\Models\Recordings;
use App\Models\RingGroups;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class RecordingsController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $output = [];
        $recordingsCollection = Recordings::where('domain_uuid', Session::get('domain_uuid'))->orderBy('insert_date')->get();
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

    public function store(StoreRecordingRequest $request)
    {
        $attributes = $request->validated();

        if ($request->greeting_filename) {
            $path = $request->greeting_filename->store(
                Session::get('domain_name'),
                'recordings'
            );
        } else {
            if (Storage::exists($request->greeting_recorded_file)) {
                $path = Session::get('domain_name').'/recorded_'.md5($request->greeting_recorded_file).'.'.pathinfo($request->greeting_recorded_file,
                        PATHINFO_EXTENSION);
                Storage::disk('recordings')->put($path, Storage::get($request->greeting_recorded_file));
            }
        }

        if (!Storage::disk('recordings')->exists($path)) {
            return response()->json([
                'error' => 401,
                'message' => 'Failed to upload file'
            ]);
        }

        $path = trim(str_replace(Session::get('domain_name'), "", $path), '/');

        $recording = new Recordings();
        $recording->recording_filename = $path;
        $recording->recording_name = $attributes['greeting_name'];
        $recording->recording_description = $attributes['greeting_description'];
        $recording->save();

        return response()->json([
            'status' => "success",
            'id' => $recording->recording_uuid,
            'name' => $recording->recording_name,
            'filename' => $path,
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
                shell_exec(base_path('ffmpeg').' -i '.$inputPath.' -acodec pcm_s16le -ac 1 -ar 16000 '.$outputPath);
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

        $recording->recording_name = $attributes['greeting_name'];
        $recording->recording_description = $attributes['greeting_description'];
        $recording->save();

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
        $path = Session::get('domain_name').'/'.$recording->recording_filename;
        $deleted = $recording->delete();
        if ($deleted) {
            Storage::disk('recordings')->delete($path);
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
