<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\DomainController;



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
    | GET /domains must return ONLY domains the user can access:
    |  - if user has "domain_all" => all domains
    |  - else if assigned domains/groups exist => only those (even if own domain not included)
    |  - else => only user's own domain
    | That filtering happens in DomainController@index (not middleware).
    */
    Route::get('/domains', [DomainController::class, 'index'])
        ->middleware('user.authorize:domain_select');

    Route::post('/domains', [DomainController::class, 'store'])
        ->middleware('user.authorize:domains_create');

    Route::get('/domains/{domain_uuid}', [DomainController::class, 'show'])
        ->middleware('user.authorize:domains_view');

    Route::put('/domains/{domain_uuid}', [DomainController::class, 'update'])
        ->middleware('user.authorize:domains_update');

    Route::delete('/domains/{domain_uuid}', [DomainController::class, 'destroy'])
        ->middleware('user.authorize:domains_delete');


    /*
    |--------------------------------------------------------------------------
    | Extensions (domain-scoped)
    |--------------------------------------------------------------------------
    | Middleware will:
    |  - enforce domain access for {domain_uuid}
    |  - enforce permission in that domain
    */
    // Route::get('/domains/{domain_uuid}/extensions', [ExtensionController::class, 'index'])
    //     ->middleware('user.authorize:extensions_list');

    // Route::post('/domains/{domain_uuid}/extensions', [ExtensionController::class, 'store'])
    //     ->middleware('user.authorize:extensions_create');

    // Route::get('/domains/{domain_uuid}/extensions/{extension_uuid}', [ExtensionController::class, 'show'])
    //     ->middleware('user.authorize:extensions_view');

    // Route::put('/domains/{domain_uuid}/extensions/{extension_uuid}', [ExtensionController::class, 'update'])
    //     ->middleware('user.authorize:extensions_update');

    // Route::delete('/domains/{domain_uuid}/extensions/{extension_uuid}', [ExtensionController::class, 'destroy'])
    //     ->middleware('user.authorize:extensions_delete');


    /*
    |--------------------------------------------------------------------------
    | Ring Groups (domain-scoped)
    |--------------------------------------------------------------------------
    */
    // Route::get('/domains/{domain_uuid}/ring-groups', [RingGroupController::class, 'index'])
    //     ->middleware('user.authorize:ring_groups_list');

    // Route::post('/domains/{domain_uuid}/ring-groups', [RingGroupController::class, 'store'])
    //     ->middleware('user.authorize:ring_groups_create');

    // Route::get('/domains/{domain_uuid}/ring-groups/{ring_group_uuid}', [RingGroupController::class, 'show'])
    //     ->middleware('user.authorize:ring_groups_view');

    // Route::put('/domains/{domain_uuid}/ring-groups/{ring_group_uuid}', [RingGroupController::class, 'update'])
    //     ->middleware('user.authorize:ring_groups_update');

    // Route::delete('/domains/{domain_uuid}/ring-groups/{ring_group_uuid}', [RingGroupController::class, 'destroy'])
    //     ->middleware('user.authorize:ring_groups_delete');


    /*
    |--------------------------------------------------------------------------
    | Voicemails (domain-scoped)
    |--------------------------------------------------------------------------
    */
    // Route::get('/domains/{domain_uuid}/voicemails', [VoicemailController::class, 'index'])
    //     ->middleware('user.authorize:voicemails_list');

    // Route::post('/domains/{domain_uuid}/voicemails', [VoicemailController::class, 'store'])
    //     ->middleware('user.authorize:voicemails_create');

    // Route::get('/domains/{domain_uuid}/voicemails/{voicemail_uuid}', [VoicemailController::class, 'show'])
    //     ->middleware('user.authorize:voicemails_view');

    // Route::put('/domains/{domain_uuid}/voicemails/{voicemail_uuid}', [VoicemailController::class, 'update'])
    //     ->middleware('user.authorize:voicemails_update');

    // Route::delete('/domains/{domain_uuid}/voicemails/{voicemail_uuid}', [VoicemailController::class, 'destroy'])
    //     ->middleware('user.authorize:voicemails_delete');

});
