<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecordingRequest;
use App\Models\Recordings;
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
        $recordingsCollection = Recordings::where('domain_uuid', Session::get('domain_uuid'))->get();
        if ($recordingsCollection) {
            foreach ($recordingsCollection as $recording) {
                $path = Session::get('domain_name').'/'.$recording->recording_filename;
                if (Storage::disk('recordings')->exists($path)) {
                    $output[] = [
                        'id' => $recording->recording_uuid,
                        'filename' => $recording->recording_filename,
                        'name' => $recording->recording_name,
                        'description' => (string)$recording->recording_description,
                    ];
                }
            }
        }

        return response()->json(['collection' => $output]);
    }

    public function store(StoreRecordingRequest $request)
    {
        $attributes = $request->validated();
        $path = $request->greeting_filename->store(
            Session::get('domain_name'),
            'recordings'
        );
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
            'recording' => $recording->recording_uuid,
            'name' => $recording->recording_name,
            'filename' => $path,
            'message' => 'Greeting created successfully'
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
