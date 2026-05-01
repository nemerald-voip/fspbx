<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\DomainController;
use App\Http\Controllers\Api\V1\ActiveCallController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\ExtensionController;
use App\Http\Controllers\Api\V1\RingGroupController;
use App\Http\Controllers\Api\V1\VoicemailController;
use App\Http\Controllers\Api\V1\PhoneNumberController;
use App\Http\Controllers\Api\V1\CdrController;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
|
*/

Route::middleware(['auth:sanctum', 'api.token.auth', 'throttle:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Domains
    |--------------------------------------------------------------------------
    */

    Route::get('/domains', [DomainController::class, 'index'])
        ->middleware('user.authorize:domain_select');

    Route::get('/domains/{domain_uuid}', [DomainController::class, 'show'])
        ->middleware('user.authorize:domain_view');

    Route::post('/domains', [DomainController::class, 'store'])
        ->middleware('user.authorize:domain_add');

    Route::patch('/domains/{domain_uuid}', [DomainController::class, 'update'])
        ->middleware('user.authorize:domain_edit');

    Route::delete('/domains/{domain_uuid}', [DomainController::class, 'destroy'])
        ->middleware('user.authorize:domain_delete');

    /*
    |--------------------------------------------------------------------------
    | Extensions (domain-scoped)
    |--------------------------------------------------------------------------
    */
    Route::get('/domains/{domain_uuid}/extensions', [ExtensionController::class, 'index'])
        ->middleware('user.authorize:extension_view');

    Route::get('/domains/{domain_uuid}/extensions/{extension_uuid}', [ExtensionController::class, 'show'])
        ->middleware('user.authorize:extension_view');

    Route::post('/domains/{domain_uuid}/extensions', [ExtensionController::class, 'store'])
        ->middleware('user.authorize:extension_add');

    Route::patch('/domains/{domain_uuid}/extensions/{extension_uuid}', [ExtensionController::class, 'update'])
        ->middleware('user.authorize:extension_edit');

    Route::delete('/domains/{domain_uuid}/extensions/{extension_uuid}', [ExtensionController::class, 'destroy'])
        ->middleware('user.authorize:extension_delete');

    /*
    |--------------------------------------------------------------------------
    | Voicemails (domain-scoped)
    |--------------------------------------------------------------------------
    */
    Route::get('/domains/{domain_uuid}/voicemails', [VoicemailController::class, 'index'])
        ->middleware('user.authorize:voicemail_domain');

    Route::get('/domains/{domain_uuid}/voicemails/{voicemail_uuid}', [VoicemailController::class, 'show'])
        ->middleware('user.authorize:voicemail_view');

    Route::post('/domains/{domain_uuid}/voicemails', [VoicemailController::class, 'store'])
        ->middleware('user.authorize:voicemail_add');

    Route::patch('/domains/{domain_uuid}/voicemails/{voicemail_uuid}', [VoicemailController::class, 'update'])
        ->middleware('user.authorize:voicemail_edit');

    Route::delete('/domains/{domain_uuid}/voicemails/{voicemail_uuid}', [VoicemailController::class, 'destroy'])
        ->middleware('user.authorize:voicemail_delete');

    /*
    |--------------------------------------------------------------------------
    | Ring Groups (domain-scoped)
    |--------------------------------------------------------------------------
    */
    Route::get('/domains/{domain_uuid}/ring-groups', [RingGroupController::class, 'index'])
        ->middleware('user.authorize:ring_group_domain');

    Route::get('/domains/{domain_uuid}/ring-groups/{ring_group_uuid}', [RingGroupController::class, 'show'])
        ->middleware('user.authorize:ring_group_view');

    Route::post('/domains/{domain_uuid}/ring-groups', [RingGroupController::class, 'store'])
        ->middleware('user.authorize:ring_group_add');

    Route::patch('/domains/{domain_uuid}/ring-groups/{ring_group_uuid}', [RingGroupController::class, 'update'])
        ->middleware('user.authorize:ring_group_edit');

    Route::delete('/domains/{domain_uuid}/ring-groups/{ring_group_uuid}', [RingGroupController::class, 'destroy'])
        ->middleware('user.authorize:ring_group_delete');

    /*
    |--------------------------------------------------------------------------
    | Devices (domain-scoped)
    |--------------------------------------------------------------------------
    */
    Route::get('/domains/{domain_uuid}/devices', [DeviceController::class, 'index'])
        ->middleware('user.authorize:device_view');

    Route::get('/domains/{domain_uuid}/devices/{device_uuid}', [DeviceController::class, 'show'])
        ->middleware('user.authorize:device_view');

    Route::post('/domains/{domain_uuid}/devices', [DeviceController::class, 'store'])
        ->middleware('user.authorize:device_add');

    Route::patch('/domains/{domain_uuid}/devices/{device_uuid}', [DeviceController::class, 'update'])
        ->middleware('user.authorize:device_edit');

    Route::delete('/domains/{domain_uuid}/devices/{device_uuid}', [DeviceController::class, 'destroy'])
        ->middleware('user.authorize:device_delete');

    /*
    |--------------------------------------------------------------------------
    | Active Calls (domain-scoped)
    |--------------------------------------------------------------------------
    */
    Route::get('/domains/{domain_uuid}/active-calls', [ActiveCallController::class, 'index'])
        ->middleware('user.authorize:domain_view');

    Route::get('/domains/{domain_uuid}/active-calls/{call_uuid}', [ActiveCallController::class, 'show'])
        ->middleware('user.authorize:domain_view');

    Route::delete('/domains/{domain_uuid}/active-calls/{call_uuid}', [ActiveCallController::class, 'destroy'])
        ->middleware('user.authorize:domain_view');

    /*
    |--------------------------------------------------------------------------
    | Phone Numbers (domain-scoped)
    |--------------------------------------------------------------------------
    */
    Route::get('/domains/{domain_uuid}/phone-numbers', [PhoneNumberController::class, 'index'])
        ->middleware('user.authorize:ring_group_domain');

    Route::get('/domains/{domain_uuid}/phone-numbers/{destination_uuid}', [PhoneNumberController::class, 'show'])
        ->middleware('user.authorize:ring_group_view');

    Route::post('/domains/{domain_uuid}/phone-numbers', [PhoneNumberController::class, 'store'])
        ->middleware('user.authorize:ring_group_add');

    Route::patch('/domains/{domain_uuid}/phone-numbers/{destination_uuid}', [PhoneNumberController::class, 'update'])
        ->middleware('user.authorize:ring_group_edit');

    Route::delete('/domains/{domain_uuid}/phone-numbers/{destination_uuid}', [PhoneNumberController::class, 'destroy'])
        ->middleware('user.authorize:ring_group_delete');

    /*
    |--------------------------------------------------------------------------
    | CDRs (domain-scoped)
    |--------------------------------------------------------------------------
    */
    Route::get('/domains/{domain_uuid}/cdrs', [CdrController::class, 'index'])
        ->middleware('user.authorize:xml_cdr_view');

    Route::get('/domains/{domain_uuid}/cdrs/{xml_cdr_uuid}', [CdrController::class, 'show'])
        ->middleware('user.authorize:xml_cdr_view');

    Route::get('/domains/{domain_uuid}/cdrs/{xml_cdr_uuid}/recording-url', [CdrController::class, 'recordingUrl'])
        ->middleware('user.authorize:xml_cdr_view');
});
