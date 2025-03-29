<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\Api\EmergencyCallController;



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


Route::group(['middleware'=>['auth:sanctum']], function(){
    Route::post('/tokens', [TokenController::class,"index"]);

    Route::resource('/emergency-calls', EmergencyCallController::class);
    Route::post('/emergency-calls/item-options', [EmergencyCallController::class, 'getItemOptions'])->name('emergency-calls.item.options');
    Route::post('/emergency-calls/bulk-delete', [EmergencyCallController::class, 'bulkDelete'])->name('emergency-calls.bulk.delete');

});

