<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
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
    public function index()
    {
        $users = User::all();

	Auth::loginUsingId('d90e1ed2-7fe8-4cb3-8e50-9b2dc0911feb');

        dd(auth()->check());

        return ("Users");
    }



   /**
     * Function is used for manual authentication from FusionPBX sign in form.
     *
     * @return ???
     */
    public function manual_auth($uuid)
    {
/*
require __DIR__.'/../../../vendor/autoload.php';
$app = require_once __DIR__ .'/../../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
*/
	$users = User::all();

dd($users);

        Auth::loginUsingId($uuid);

        dd(auth()->check());

        return ("Users");
    }

}
