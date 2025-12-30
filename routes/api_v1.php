<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\DomainController;
use App\Http\Controllers\Api\V1\ExtensionController;
use App\Http\Controllers\Api\V1\VoicemailController;



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
        ->middleware('user.authorize:extension_domain');

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
    | Ring Groups (domain-scoped)
    |--------------------------------------------------------------------------
    */


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
});
