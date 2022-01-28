<?php

namespace App\Http\Controllers;

use App\Models\Users;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TokenController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $fields = $request->validate ([
            'user_email' => 'required',
            'password' => 'required'
        ]);

        $user= Users::where('user_email', $request->user_email)->first();

        if (!$user || !password_verify($request->password, $user->password)){
            return $this->sendError('Athentication failed.',[],401);
        }
        return $user->tokens;


    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
            
        $fields = $request->validate ([
            'user_email' => 'required|string|email|max:255|',
            'password' => 'required'
        ]);

        $user= Users::where('user_email', $request->user_email)->first();

        if (!$user || !password_verify($request->password, $user->password)){
            return $this->sendError('Athentication failed.',[],401);
        }
        $token = $user->createToken('auth_token')->plainTextToken;
        
        return $this->sendResponse([
            'name' => $user->username,
            'access_token' => $token, 
            'token_type' => 'Bearer', 
            ], 
            'Token generated successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
