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
        // print "/users<br>";
        // //$cookie = $request->cookie('laravel_session');
        // $cookies = $request->cookie();
        // print_r($cookies);

        //dd(Auth::check());
        if (Auth::check()) {
            return ("User logged in");
        } else {
            return ("No session");
        }
    }


}
