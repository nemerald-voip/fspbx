<?php

use App\Models\DomainGroups;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\GroupsController;
use App\Http\Controllers\UserLogsController;
use App\Http\Controllers\ExtensionsController;
use App\Http\Controllers\RingGroupsController;
use App\Http\Controllers\DomainGroupsController;
use App\Http\Controllers\BusinessHoursController;
use App\Http\Controllers\Api\HolidayHoursController;
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
    // Tokens
    Route::resource('/tokens', TokenController::class);
    Route::post('tokens/bulk-delete', [TokenController::class, 'bulkDelete'])->name('tokens.bulk.delete');

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


    // Business Hours
    Route::post('business-hours', [BusinessHoursController::class, 'store'])->name('business-hours.store');
    Route::put('business-hours/{business_hour}', [BusinessHoursController::class, 'update'])->name('business-hours.update');
    Route::post('business-hours/item-options', [BusinessHoursController::class, 'getItemOptions'])->name('business-hours.item.options');
    Route::post('business-hours/bulk-delete', [BusinessHoursController::class, 'bulkDelete'])->name('business-hours.bulk.delete');
    Route::post('business-hours/select-all', [BusinessHoursController::class, 'selectAll'])->name('business-hours.select.all');


    // Holiday Hours
    Route::resource('/holiday-hours', HolidayHoursController::class);
    Route::post('/holiday-hours/item-options', [HolidayHoursController::class, 'getItemOptions'])->name('holiday-hours.item.options');
    Route::post('/holiday-hours/bulk-delete', [HolidayHoursController::class, 'bulkDelete'])->name('holiday-hours.bulk.delete');

    // Groups
    Route::post('groups', [GroupsController::class, 'store'])->name('groups.store');
    Route::put('groups/{group}', [GroupsController::class, 'update'])->name('groups.update');
    Route::post('groups/item-options', [GroupsController::class, 'getItemOptions'])->name('groups.item.options');
    Route::post('groups/bulk-delete', [GroupsController::class, 'bulkDelete'])->name('groups.bulk.delete');
    Route::post('groups/select-all', [GroupsController::class, 'selectAll'])->name('groups.select.all');

    // Domain Groups
    Route::post('domain-groups', [DomainGroupsController::class, 'store'])->name('domain-groups.store');
    Route::put('domain-groups/{domain_group}', [DomainGroupsController::class, 'update'])->name('domain-groups.update');
    Route::post('domain-groups/item-options', [DomainGroupsController::class, 'getItemOptions'])->name('domain-groups.item.options');
    Route::post('domain-groups/bulk-delete', [DomainGroupsController::class, 'bulkDelete'])->name('domain-groups.bulk.delete');
    Route::post('domain-groups/select-all', [DomainGroupsController::class, 'selectAll'])->name('domain-groups.select.all');

    // Users
    Route::post('users', [UsersController::class, 'store'])->name('users.store');
    Route::put('users/{user}', [UsersController::class, 'update'])->name('users.update');
    Route::post('users/item-options', [UsersController::class, 'getItemOptions'])->name('users.item.options');
    Route::post('users/bulk-delete', [UsersController::class, 'bulkDelete'])->name('users.bulk.delete');
    Route::post('users/select-all', [UsersController::class, 'selectAll'])->name('users.select.all');

    // Extensions
    Route::post('extensions', [ExtensionsController::class, 'store'])->name('extensions.store');
    Route::put('extensions/{extension}', [ExtensionsController::class, 'update'])->name('extensions.update');
    Route::post('extensions/item-options', [ExtensionsController::class, 'getItemOptions'])->name('extensions.item.options');
    Route::post('extensions/bulk-delete', [ExtensionsController::class, 'bulkDelete'])->name('extensions.bulk.delete');
    Route::post('extensions/select-all', [ExtensionsController::class, 'selectAll'])->name('extensions.select.all');
    Route::get('/extensions/registrations', [ExtensionsController::class, 'registrations'])->name('extensions.registrations');
    Route::get('/extensions/{extension}/devices', [ExtensionsController::class, 'devices'])->name('extensions.devices');

    // User logs
    Route::post('user-logs/select-all', [UserLogsController::class, 'selectAll'])->name('user-logs.select.all');

    // Devices 
    Route::post('devices/item-options', [DeviceController::class, 'getItemOptions'])->name('devices.item.options');
    Route::post('devices/assign', [DeviceController::class, 'assign'])->name('devices.assign');
    Route::post('devices/bulk-unassign', [DeviceController::class, 'bulkUnassign'])->name('devices.bulk.unassign');
});
