<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PolycomLogController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request,$name)
    {
        $putdata = fopen("php://input", "r");
        if (!Storage::disk('polycom_log_directory')->put($name,$putdata)){
            abort(404);
        } else {
            return response('OK', 200);
        }
    }


    /**
     * Respond to the HEAD request
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request,$name)
    {
        // check if the file exists
        if (!Storage::disk('polycom_log_directory')->exists($name)) {
            abort(404);
        } else {
            return response('OK', 200);
        }


    }
}
