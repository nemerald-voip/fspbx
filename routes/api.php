<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TokenController;



/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::post('/tokens/create', [TokenController::class,"create"]);

//Route::post('/tokens', [TokenController::class,"index"]);

Route::group(['middleware'=>['auth:sanctum']], function(){
    Route::post('/tokens', [TokenController::class,"index"]);
    // Route used for submitting authnetication request from FusionPBX login
    //Route::post('/users/manual_auth', [UsersController::class, 'manual_auth'])->name('manual_auth');
});
 //Route::post('/users/manual_auth', [UsersController::class, 'manual_auth'])->name('manual_auth');  

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
