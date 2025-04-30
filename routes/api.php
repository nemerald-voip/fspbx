<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\RingGroupsController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\AccountSettingsController;
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


Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::post('/tokens', [TokenController::class, "index"]);

    // Emergency calls
    Route::resource('/emergency-calls', EmergencyCallController::class);
    Route::post('/emergency-calls/item-options', [EmergencyCallController::class, 'getItemOptions'])->name('emergency-calls.item.options');
    Route::post('/emergency-calls/bulk-delete', [EmergencyCallController::class, 'bulkDelete'])->name('emergency-calls.bulk.delete');
    Route::post('/emergency-calls/check-service-status', [EmergencyCallController::class, 'checkServiceStatus'])->name('emergency-calls.check.service.status');


    // Ring Groups
    Route::post('ring-groups', [RingGroupsController::class, 'store'])->name('ring-groups.store');
    Route::put('ring-groups/{ring_group}', [RingGroupsController::class, 'update'])->name('ring-groups.update');
    Route::delete('ring-groups/{ring_group}', [RingGroupsController::class, 'destroy'])->name('ring-groups.destroy');
    Route::post('ring-groups/item-options', [RingGroupsController::class, 'getItemOptions'])->name('ring-groups.item.options');
    Route::post('ring-groups/bulk-delete', [RingGroupsController::class, 'bulkDelete'])->name('ring-groups.bulk.delete');
    Route::post('ring-groups/select-all', [RingGroupsController::class, 'selectAll'])->name('ring-groups.select.all');

    // Account Settings
    Route::put('account-settings/update', [AccountSettingsController::class, 'update'])->name('account-settings.update');

    // System Settings
    Route::put('system-settings/update', [SystemSettingsController::class, 'update'])->name('system-settings.update');
    Route::get('system-settings/payment_gateways', [SystemSettingsController::class, 'getPaymentGatewayData'])->name('system-settings.payment_gateways');
});
