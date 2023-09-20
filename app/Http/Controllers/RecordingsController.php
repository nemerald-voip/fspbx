<?php

namespace App\Http\Controllers;

use App\Models\Recordings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class RecordingsController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return redirect('/');
    }

    /**
     * Get recordings greeting.
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function getRecordings(string $filename = null)
    {
        if ($filename) {
            $path = Session::get('domain_name').'/'.$filename;
            if (!Storage::disk('recordings')->exists($path)) {
                abort(404);
            }
            $file = Storage::disk('recordings')->path($path);
            $type = Storage::disk('recordings')->mimeType($path);
            $response = \Illuminate\Support\Facades\Response::make(file_get_contents($file), 200);
            $response->header("Content-Type", $type);
            return $response;
        } else {
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
                            'description' => $recording->recording_description,
                        ];
                    }
                }
            }

            return response()->json(['collection' => $output]);
        }
    }
}
