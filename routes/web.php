<?php


use App\Http\Controllers\PhoneNumbersController;
use App\Http\Controllers\RecordingsController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AppsController;
use App\Http\Controllers\CdrsController;
use App\Http\Controllers\FaxesController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\GroupsController;
use App\Http\Controllers\ContactsController;
use App\Http\Controllers\FaxQueueController;
use App\Http\Controllers\MessagesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\VoicemailController;
use App\Http\Controllers\EmailQueueController;
use App\Http\Controllers\ExtensionsController;
use App\Http\Controllers\PolycomLogController;
use App\Http\Controllers\RingGroupsController;
use App\Http\Controllers\DomainGroupsController;
use App\Http\Controllers\UserSettingsController;
use App\Http\Controllers\MessageSettingsController;
use App\Http\Controllers\VoicemailMessagesController;
use App\Http\Controllers\Auth\ForgotPasswordController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/extensions/callerid', [ExtensionsController::class, 'callerID'])->withoutMiddleware(['auth', 'web'])->name('callerID');
Route::post('/extensions/{extension}/callerid/update/', [ExtensionsController::class, 'updateCallerID'])->withoutMiddleware(['auth', 'web'])->name('updateCallerID');

//Polycom log handling
Route::put('/polycom/log/{name}', [PolycomLogController::class, 'store'])->withoutMiddleware(['auth', 'web'])->name('log.store');
Route::get('/polycom/log/{name}', [PolycomLogController::class, 'show'])->withoutMiddleware(['auth', 'web'])->name('log.get');
// Route::get('/extensions', [ExtensionsController::class, 'index']) ->name('extensionsList');

// Webhooks
Route::webhooks('webhook/postmark', 'postmark');
Route::webhooks('webhook/commio/sms', 'commio_messaging');

// Routes for 2FA email challenge. Used as a backup when 2FA is not enabled.
Route::get('/email-challenge', [App\Http\Controllers\Auth\EmailChallengeController::class, 'create'])->name('email-challenge.login');
Route::put('/email-challenge', [App\Http\Controllers\Auth\EmailChallengeController::class, 'update'])
    ->middleware('throttle:2,1')
    ->name('email-challenge.new-code');
Route::post('/email-challenge', [App\Http\Controllers\Auth\EmailChallengeController::class, 'store']);

// Route::get('preview-email', function () {
//     $markdown = new \Illuminate\Mail\Markdown(view(), config('mail.markdown'));
//     $data = "Your data to be use in blade file";
//     return $markdown->render("emails.app.credentials");
//    });

Route::group(['middleware' => 'auth'], function () {

    // Extensions
    Route::resource('extensions', ExtensionsController::class);
    Route::post('/extensions/import', [ExtensionsController::class, 'import'])->name('extensions.import');
    Route::post('/extensions/{extension}/assign-device', [ExtensionsController::class, 'assignDevice'])->name('extensions.assign-device');
    Route::post('/extensions/{extension}/device', [ExtensionsController::class, 'oldStoreDevice'])->name('extensions.store-device');
    Route::get('/extensions/{extension}/device/{device}/edit', [ExtensionsController::class, 'oldEditDevice'])->name('extensions.edit-device');
    Route::put('/extensions/{extension}/device/{device}', [ExtensionsController::class, 'oldUpdateDevice'])->name('extensions.update-device');
    Route::delete('/extensions/{extension}/unassign/{deviceLine}/device', [ExtensionsController::class, 'unAssignDevice'])->name('extensions.unassign-device');
    Route::delete('/extensions/{extension}/callforward/{type}', [ExtensionsController::class, 'clearCallforwardDestination'])->name('extensions.clear-callforward-destination');
    Route::post('/extensions/{extension}/send-event-notify', [ExtensionsController::class, 'sendEventNotify'])->name('extensions.send-event-notify');
    Route::post('/extensions/send-event-notify-all', [ExtensionsController::class, 'sendEventNotifyAll'])->name('extensions.send-event-notify-all');

    // Call Detail Records
    Route::get('/call-detail-records', [CdrsController::class, 'index'])->name('cdrs.index');
    Route::post('/call-detail-records', [CdrsController::class, 'index'])->name('cdrs.download');
    Route::get('/call-detail-records/file/{filePath}/{fileName}', [CdrsController::class, 'serveRecording'])->name('serve.recording');

    //Domains
    Route::get('domains/extensions', [DomainController::class, 'countExtensionsInDomains']);

    //Users
    Route::resource('users', UsersController::class);
    Route::post('user/{user}/settings', [UserSettingsController::class, 'store'])->name('users.settings.store');
    Route::delete('user/settings/{setting}', [UserSettingsController::class, 'destroy'])->name('users.settings.destroy');
    Route::post('user/password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('users.password.email');

    // Groups
    Route::resource('groups', GroupsController::class);

    //Fax
    Route::resource('faxes', FaxesController::class);
    Route::get('/faxes/newfax/create', [FaxesController::class, 'new'])->name('faxes.newfax');
    Route::get('/faxes/inbox/{id}', [FaxesController::class, 'inbox'])->name('faxes.inbox.list');
    Route::get('/faxes/sent/{id}', [FaxesController::class, 'sent'])->name('faxes.sent.list');
    Route::get('/faxes/active/{id}', [FaxesController::class, 'active'])->name('faxes.active.list');
    Route::get('/faxes/log/{id}', [FaxesController::class, 'log'])->name('faxes.log.list');
    Route::delete('/faxes/deleteSentFax/{id}', [FaxesController::class, 'deleteSentFax'])->name('faxes.file.deleteSentFax');
    Route::delete('/faxes/deleteReceivedFax/{id}', [FaxesController::class, 'deleteReceivedFax'])->name('faxes.file.deleteReceivedFax');
    Route::delete('/faxes/deleteFaxLog/{id}', [FaxesController::class, 'deleteFaxLog'])->name('faxes.file.deleteFaxLog');
    Route::get('/fax/inbox/{file}/download', [FaxesController::class, 'downloadInboxFaxFile'])->name('downloadInboxFaxFile');
    Route::get('/fax/sent/{file}/download', [FaxesController::class, 'downloadSentFaxFile'])->name('downloadSentFaxFile');
    Route::get('/fax/sent/{faxQueue}/{status?}', [FaxesController::class, 'updateStatus'])->name('faxes.file.updateStatus');
    Route::post('/faxes/send', [FaxesController::class, 'sendFax'])->name('faxes.sendFax');

    // Domain Groups
    Route::resource('domaingroups', DomainGroupsController::class);

    //Voicemails
    Route::resource('voicemails', VoicemailController::class);

    // Voicemail Messages
    Route::get('/voicemails/{voicemail}/messages/', [VoicemailMessagesController::class, 'index'])->name('voicemails.messages.index');
    Route::delete('/voicemails/messages/{message}', [VoicemailMessagesController::class, 'destroy'])->name('voicemails.messages.destroy');
    Route::get('/voicemails/messages/{message}', [VoicemailMessagesController::class, 'getVoicemailMessage'])->name('getVoicemailMessage');
    Route::get('/voicemails/messages/{message}/download', [VoicemailMessagesController::class, 'downloadVoicemailMessage'])->name('downloadVoicemailMessage');
    Route::get('/voicemails/messages/{message}/delete', [VoicemailMessagesController::class, 'deleteVoicemailMessage'])->name('deleteVoicemailMessage');


    // SIP Credentials
    Route::get('/extensions/{extension}/sip/show', [ExtensionsController::class, 'sipShow'])->name('extensions.sip.show');

    // Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
    Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
    Route::get('/logout', [App\Http\Controllers\Auth\LoginController::class, 'logout']);

    Route::post('/domains/switch', [DomainController::class, 'switchDomain'])->name('switchDomain');
    Route::get('/domains/switch', function () {
        return redirect('/dashboard');
    });
    Route::get('/domains/switch/{domain}', [DomainController::class, 'switchDomainFusionPBX'])->name('switchDomainFusionPBX');
    Route::get('/domains/filter/', [DomainController::class, 'filterDomainsFusionPBX'])->name('filterDomainsFusionPBX');

    //Devices
    Route::get('/devices/options', [DeviceController::class, 'options'])->name('devices.options');
    Route::post('/devices/bulk-update', [DeviceController::class, 'bulkUpdate'])->name('devices.bulk.update');
    Route::post('/devices/bulk-delete', [DeviceController::class, 'bulkDelete'])->name('devices.bulk.delete');
    Route::resource('devices', DeviceController::class);
    Route::post('/devices/restart', [DeviceController::class, 'restart'])->name('devices.restart');
    Route::post('/devices/select-all', [DeviceController::class, 'selectAll'])->name('devices.select.all');


    //Route::get('/phone-numbers/options', [PhoneNumbersController::class, 'options'])->name('phoneNumbers.options');
    //Route::put('/phone-numbers/bulk-update', [DeviceController::class, 'bulkUpdate'])->name('phoneNumbers.bulkUpdate');
    Route::resource('phone-numbers', PhoneNumbersController::class);

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    //Users
    // Route::get('/users', [UsersController::class, 'index']) ->name('usersList');
    //Route::get('/users/create', [UsersController::class, 'createUser']) ->name('usersCreateUser');
    //Route::get('/users/edit/{id}', [UsersController::class, 'editUser']) ->name('editUser');
    // Route::post('/saveUser', [UsersController::class, 'saveUser']) ->name('saveUser');
    // Route::post('/updateUser', [UsersController::class, 'updateUser']) ->name('updateUser');
    Route::post('/deleteUser', [UsersController::class, 'deleteUser'])->name('deleteUser');
    Route::post('/addSetting', [UsersController::class, 'addSetting'])->name('addSetting');


    //Voicemails
    Route::post('/voicemails/greetings/upload/{voicemail}', [VoicemailController::class, 'uploadVoicemailGreeting'])->name('uploadVoicemailGreeting');
    Route::get('/voicemails/{voicemail}/greetings/{filename}', [VoicemailController::class, 'getVoicemailGreeting'])->name('getVoicemailGreeting');
    Route::get('/voicemails/{voicemail}/greetings/{filename}/download', [VoicemailController::class, 'downloadVoicemailGreeting'])->name('downloadVoicemailGreeting');
    Route::get('/voicemails/{voicemail}/greetings/{filename}/delete', [VoicemailController::class, 'deleteVoicemailGreeting'])->name('deleteVoicemailGreeting');

    //Apps
    Route::get('/apps', [AppsController::class, 'index'])->name('appsStatus');
    Route::post('/apps/organization/create', [AppsController::class, 'createOrganization'])->name('appsCreateOrganization');
    Route::delete('/apps/organization/{domain}', [AppsController::class, 'destroyOrganization'])->name('appsDestroyOrganization');
    Route::get('/apps/organization/', [AppsController::class, 'getOrganizations'])->name('appsGetOrganizations');
    Route::post('/apps/organization/sync', [AppsController::class, 'syncOrganizations'])->name('appsSyncOrganizations');
    Route::post('/apps/users/{extension}', [AppsController::class, 'mobileAppUserSettings'])->name('mobileAppUserSettings');
    //Route::get('/apps/organization/update', [AppsController::class, 'updateOrganization']) ->name('appsUpdateOrganization');
    Route::post('/apps/connection/create', [AppsController::class, 'createConnection'])->name('appsCreateConnection');
    Route::get('/apps/connection/update', [AppsController::class, 'updateConnection'])->name('appsUpdateConnection');
    Route::post('/apps/user/create', [AppsController::class, 'createUser'])->name('appsCreateUser');
    Route::post('/apps/{domain}/user/sync', [AppsController::class, 'syncUsers'])->name('appsSyncUsers');
    Route::delete('/apps/users/{extension}', [AppsController::class, 'deleteUser'])->name('appsDeleteUser');
    Route::post('/apps/users/{extension}/resetpassword', [AppsController::class, 'ResetPassword'])->name('appsResetPassword');
    Route::post('/apps/users/{extension}/status', [AppsController::class, 'SetStatus'])->name('appsSetStatus');
    Route::get('/apps/email', [AppsController::class, 'emailUser'])->name('emailUser');

    // Contacts
    Route::get('/contacts', [ContactsController::class, 'index'])->name('contacts.list');
    Route::delete('/contacts/{id}', [ContactsController::class, 'destroy'])->name('contacts.destroy');
    Route::post('/contacts/import', [ContactsController::class, 'import'])->name('contacts.import');


    // SMS for testing
    // Route::get('/sms/ringotelwebhook', [SmsWebhookController::class,"messageFromRingotel"]);

    // Messages
    Route::get('/messages', [MessagesController::class, 'index'])->name('messagesStatus');

    // Message Settings
    Route::get('/message-settings', [MessageSettingsController::class, 'index'])->name('messages.settings');
    Route::put('/message-settings/{setting}', [MessageSettingsController::class, 'update'])->name('messages.settings.update');
    Route::post('/message-settings', [MessageSettingsController::class, 'store'])->name('messages.settings.store');
    Route::delete('/message-settings/{setting}', [MessageSettingsController::class, 'destroy'])->name('messages.settings.destroy');
    Route::post('/message-settings/select-all', [MessageSettingsController::class, 'selectAll'])->name('messages.settings.select.all');
    Route::post('/message-settings/bulk-delete', [MessageSettingsController::class, 'bulkDelete'])->name('messages.settings.bulk.delete');
    Route::post('/message-settings/bulk-update', [MessageSettingsController::class, 'bulkUpdate'])->name('messages.settings.bulk.update');

    // Email Queues
    Route::get('emailqueue', [EmailQueueController::class, 'index'])->name('emailqueue.list');
    Route::delete('emailqueue/{id}', [EmailQueueController::class, 'delete'])->name('emailqueue.destroy');
    Route::get('emailqueue/{emailQueue}/{status?}', [EmailQueueController::class, 'updateStatus'])->name('emailqueue.updateStatus');

    // Fax Queue
    Route::get('faxqueue', [FaxQueueController::class, 'index'])->name('faxQueue.list');
    Route::delete('faxqueue/{id}', [FaxQueueController::class, 'destroy'])->name('faxQueue.destroy');
    Route::get('faxqueue/{faxQueue}/{status?}', [FaxQueueController::class, 'updateStatus'])->name('faxQueue.updateStatus');

    // Ring Groups
    Route::resource('ring-groups', RingGroupsController::class);

    // Recordings
    Route::get('recordings', [RecordingsController::class, 'index'])->name('recordings.index');
    Route::get('recordings/i-{recording}', [RecordingsController::class, 'show'])->name('recordings.show');
    Route::get('recordings/{filename}', [RecordingsController::class, 'file'])->name('recordings.file');
    Route::delete('recordings/{recording}', [RecordingsController::class, 'destroy'])->name('recordings.destroy');
    Route::post('recordings', [RecordingsController::class, 'store'])->name('recordings.store');
    Route::post('recordings/storeBlob', [RecordingsController::class, 'storeBlob'])->name('recordings.storeBlob');
    Route::put('recordings/{recording}', [RecordingsController::class, 'update'])->name('recordings.update');
    Route::put('recordings/{recording}/{entity}/{entityid}', [RecordingsController::class, 'use'])->name('recordings.use');

    //Route::get('/recordings/{filename?}', [RecordingsController::class, 'getRecordings']) ->name('getRecordings');
    //Route::delete('recordings/{filename}',[RecordingsController::class, 'destroy'])->name('faxQueue.destroy');
});

// Route::group(['prefix' => '/'], function () {
//     Route::get('', [RoutingController::class, 'index'])->name('root');
//     Route::get('{first}/{second}/{third}', [RoutingController::class, 'thirdLevel'])->name('third');
//     Route::get('{first}/{second}', [RoutingController::class, 'secondLevel'])->name('second');
//     Route::get('/any/{any}', [RoutingController::class, 'root'])->name('any');
// });

// Auth::routes();

//Route::get('/dashboard', [App\Http\Controllers\HomeController::class, 'index'])->name('dashboard');
