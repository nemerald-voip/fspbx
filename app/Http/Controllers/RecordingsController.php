<?php

namespace App\Http\Controllers;

use cache;
use App\Models\Domain;
use App\Models\Extensions;
use App\Models\Voicemails;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\VoicemailGreetings;
use Illuminate\Support\Facades\Log;
use App\Models\VoicemailDestinations;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

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
     * @return \Illuminate\Http\Response
     */
    public function getRecordings(string $filename)
    {
        $path = Session::get('domain_name') . '/' . $filename;

        if (!Storage::disk('recordings')->exists($path)) abort(404);

        $file = Storage::disk('recordings')->path($path);
        $type = Storage::disk('recordings')->mimeType($path);

        $response = \Illuminate\Support\Facades\Response::make(file_get_contents($file), 200);
        $response->header("Content-Type", $type);
        return $response;
    }
}
