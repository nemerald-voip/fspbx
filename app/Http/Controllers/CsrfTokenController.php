<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;

class CsrfTokenController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        try {
            $request->session()->regenerateToken();

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Token refreshed']],
                'token' => $request->session()->token(),
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to refresh token']]
            ], 500); // 500 Internal Server Error for any other errors
        }

        return response()->json([
            'success' => false,
            'errors' => ['server' => ['Failed to refresh token']]
        ], 500); // 500 Internal Server Error for any other errors

        
    }
}
