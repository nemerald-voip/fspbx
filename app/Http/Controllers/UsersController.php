<?php

namespace App\Http\Controllers;


use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Http\Kernel;

class UsersController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('auth');
    }

   /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(Request $request)
    {
 
        return view('layouts.users.list');

    }


    /**
     * Show the create user form.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function createUser(Request $request)
    {
 
        return view('layouts.users.createuser');

    }


    

}
