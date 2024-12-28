<?php

use Aws\Sns\Message;
use App\Models\WhitelistedNumbers;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AppsController;
use App\Http\Controllers\CdrsController;
use App\Http\Controllers\FaxesController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\GroupsController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ContactsController;
use App\Http\Controllers\FaxQueueController;
use App\Http\Controllers\FirewallController;
use App\Http\Controllers\MessagesController;
use App\Http\Controllers\CsrfTokenController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\VoicemailController;
use App\Http\Controllers\EmailQueueController;
use App\Http\Controllers\ExtensionsController;
use App\Http\Controllers\PolycomLogController;
use App\Http\Controllers\RecordingsController;
use App\Http\Controllers\RingGroupsController;
use App\Http\Controllers\ActiveCallsController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ProFeaturesController;
use App\Http\Controllers\DomainGroupsController;
use App\Http\Controllers\PhoneNumbersController;
use App\Http\Controllers\UserSettingsController;
use App\Http\Controllers\RegistrationsController;
use App\Http\Controllers\AppsCredentialsController;
use App\Http\Controllers\MessageSettingsController;
use App\Http\Controllers\SansayActiveCallsController;
use App\Http\Controllers\VoicemailMessagesController;
use App\Http\Controllers\CallRoutingOptionsController;
use App\Http\Controllers\WhitelistedNumbersController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\ExtensionStatisticsController;
use App\Http\Controllers\SansayRegistrationsController;

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
Route::webhooks('webhook/sinch/sms', 'sinch_messaging');
Route::webhooks('/sms/ringotelwebhook', 'ringotel_messaging');

// Routes for 2FA email challenge. Used as a backup when 2FA is not enabled.
Route::get('/email-challenge', [App\Http\Controllers\Auth\EmailChallengeController::class, 'create'])->name('email-challenge.login');
Route::put('/email-challenge', [App\Http\Controllers\Auth\EmailChallengeController::class, 'update'])
    ->middleware('throttle:2,1')
    ->name('email-challenge.new-code');
Route::post('/email-challenge', [App\Http\Controllers\Auth\EmailChallengeController::class, 'store']);

// Csrf token
Route::get('/csrf-token/refresh', [CsrfTokenController::class, 'store']);

// Get mobile app password
Route::get('/mobile-app/get-password/{token}', [AppsCredentialsController::class, 'getPasswordByToken'])->name('appsGetPasswordByToken');
Route::post('/mobile-app/get-password/{token}', [AppsCredentialsController::class, 'retrievePasswordByToken'])->name('appsRetrievePasswordByToken');

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
    Route::post('/call-detail-records/export', [CdrsController::class, 'export'])->name('cdrs.export');
    Route::post('/call-detail-records/item-options', [CdrsController::class, 'getItemOptions'])->name('cdrs.item.options');


    //Extension Statistics
    Route::get('/extension-statistics', [ExtensionStatisticsController::class, 'index'])->name('extension-statistics.index');

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
    Route::post('voicemails/item-options', [VoicemailController::class, 'getItemOptions'])->name('voicemails.item.options');
    Route::post('/voicemails/{voicemail}/text-to-speech', [VoicemailController::class, 'textToSpeech'])->name('voicemails.textToSpeech');
    Route::post('/voicemails/{voicemail}/text-to-speech-for-name', [VoicemailController::class, 'textToSpeechForName'])->name('voicemails.textToSpeechForName');
    Route::get('/voicemail/{domain}/{voicemail_id}/{file}', [VoicemailController::class, 'serveVoicemailFile'])->name('voicemail.file.serve');
    Route::post('/voicemail/{domain}/{voicemail}/{file}', [VoicemailController::class, 'applyVoicemailFile'])->name('voicemail.file.apply');
    Route::post('/voicemail/{domain}/{voicemail}/{file}/name', [VoicemailController::class, 'applyVoicemailFileForName'])->name('voicemail.file.name.apply');
    Route::post('/voicemail/{voicemail}/greeting', [VoicemailController::class, 'getVoicemailGreeting'])->name('voicemail.greeting');
    Route::post('voicemails/{voicemail}/delete-greeting', [VoicemailController::class, 'deleteGreeting'])->name('voicemails.deleteGreeting');
    Route::post('voicemails/{voicemail}/upload-greeting', [VoicemailController::class, 'uploadGreeting'])->name('voicemails.uploadGreeting');
    Route::post('/voicemail/{voicemail}/recorde-name', [VoicemailController::class, 'getRecordedName'])->name('voicemail.recorded_name');
    Route::post('voicemails/{voicemail}/delete-recorded-name', [VoicemailController::class, 'deleteRecordedName'])->name('voicemails.deleteRecordedName');
    Route::post('voicemails/{voicemail}/upload-recorded-name', [VoicemailController::class, 'uploadRecordedName'])->name('voicemails.uploadRecordedName');

    // Voicemail Messages
    Route::get('/voicemails/{voicemail}/messages/', [VoicemailMessagesController::class, 'index'])->name('voicemails.messages.index');
    Route::delete('/voicemails/messages/{message}', [VoicemailMessagesController::class, 'destroy'])->name('voicemails.messages.destroy');
    Route::get('/voicemails/messages/{message}', [VoicemailMessagesController::class, 'getVoicemailMessage'])->name('voicemail.message');
    Route::post('/voicemails/messages/get-url', [VoicemailMessagesController::class, 'getVoicemailMessageUrl'])->name('voicemail.message.url');
    Route::get('/voicemails/messages/{message}/download', [VoicemailMessagesController::class, 'downloadVoicemailMessage'])->name('downloadVoicemailMessage');
    Route::get('/voicemails/messages/{message}/delete', [VoicemailMessagesController::class, 'deleteVoicemailMessage'])->name('deleteVoicemailMessage');
    Route::post('/voicemails/messages/bulk-delete', [VoicemailMessagesController::class, 'bulkDelete'])->name('voicemails.messages.bulk.delete');
    Route::post('/voicemails/messages/select-all', [VoicemailMessagesController::class, 'selectAll'])->name('voicemails.messages.select.all');




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

    Route::resource('phone-numbers', PhoneNumbersController::class);
    Route::post('/phone-numbers/select-all', [PhoneNumbersController::class, 'selectAll'])->name('phone-numbers.select.all');
    Route::post('/phone-numbers/bulk-update', [PhoneNumbersController::class, 'bulkUpdate'])->name('phone-numbers.bulk.update');
    Route::post('/phone-numbers/bulk-delete', [PhoneNumbersController::class, 'bulkDelete'])->name('phone-numbers.bulk.delete');
    Route::post('phone-numbers/item-options', [PhoneNumbersController::class, 'getItemOptions'])->name('phone-numbers.item.options');


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
    Route::resource('apps', AppsController::class);
    Route::post('apps/item-options', [AppsController::class, 'getItemOptions'])->name('apps.item.options');
    Route::post('/apps/organization/create', [AppsController::class, 'createOrganization'])->name('apps.organization.create');
    Route::put('/apps/organization/update', [AppsController::class, 'updateOrganization'])->name('apps.organization.update');
    Route::post('/apps/organization/destroy', [AppsController::class, 'destroyOrganization'])->name('apps.organization.destroy');
    // Route::get('/apps/organization/', [AppsController::class, 'getOrganizations'])->name('appsGetOrganizations');
    Route::post('/apps/organization/sync', [AppsController::class, 'syncOrganizations'])->name('appsSyncOrganizations');
    Route::post('/apps/users/{extension}', [AppsController::class, 'mobileAppUserSettings'])->name('mobileAppUserSettings');
    //Route::get('/apps/organization/update', [AppsController::class, 'updateOrganization']) ->name('appsUpdateOrganization');
    Route::post('/apps/connection/create', [AppsController::class, 'createConnection'])->name('apps.connection.create');
    Route::put('/apps/connection/update', [AppsController::class, 'updateConnection'])->name('apps.connection.update');
    Route::post('/apps/connection/delete', [AppsController::class, 'destroyConnection'])->name('apps.connection.destroy');
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
    Route::resource('messages', MessagesController::class);
    Route::post('/messages/retry', [MessagesController::class, 'retry'])->name('messages.retry');
    Route::post('/messages/bulk-update', [DeviceController::class, 'bulkUpdate'])->name('messages.bulk.update');
    Route::post('/messages/bulk-delete', [DeviceController::class, 'bulkDelete'])->name('messages.bulk.delete');
    Route::post('/messages/select-all', [DeviceController::class, 'selectAll'])->name('messages.select.all');



    // Message Settings
    Route::get('/message-settings', [MessageSettingsController::class, 'index'])->name('messages.settings');
    Route::put('/message-settings/{setting}', [MessageSettingsController::class, 'update'])->name('messages.settings.update');
    Route::post('/message-settings', [MessageSettingsController::class, 'store'])->name('messages.settings.store');
    Route::delete('/message-settings/{setting}', [MessageSettingsController::class, 'destroy'])->name('messages.settings.destroy');
    Route::post('/message-settings/select-all', [MessageSettingsController::class, 'selectAll'])->name('messages.settings.select.all');
    Route::post('/message-settings/bulk-delete', [MessageSettingsController::class, 'bulkDelete'])->name('messages.settings.bulk.delete');
    Route::post('/message-settings/bulk-update', [MessageSettingsController::class, 'bulkUpdate'])->name('messages.settings.bulk.update');


    // Firewall
    Route::resource('firewall', FirewallController::class);
    Route::post('firewall/unblock', [FirewallController::class, 'destroy'])->name('firewall.unblock');
    Route::post('/firewall/block', [FirewallController::class, 'store'])->name('firewall.block');
    Route::post('/firewall/select-all', [FirewallController::class, 'selectAll'])->name('firewall.select.all');



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

    // Activity Log
    Route::resource('activities', ActivityLogController::class);
    Route::post('/activities/bulk-delete', [ActivityLogController::class, 'bulkDelete'])->name('activities.bulk.delete');
    Route::post('/activities/select-all', [ActivityLogController::class, 'selectAll'])->name('activities.select.all');

    // Reports
    Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::post('reports/generate', [ReportsController::class, 'store'])->name('reports.generate');

    // Call Routing options
    Route::post('/call-routing-options', [CallRoutingOptionsController::class, 'getRoutingOptions'])->name('routing.options');

    // Registrations
    Route::resource('registrations', RegistrationsController::class);
    Route::post('/registrations/select-all', [RegistrationsController::class, 'selectAll'])->name('registrations.select.all');
    Route::post('/registrations/action', [RegistrationsController::class, 'handleAction'])->name('registrations.action');

    // Sansay Registrations
    Route::resource('sansay/registrations', SansayRegistrationsController::class)->names([
        'index' => 'sansay.registrations.index',
        'create' => 'sansay.registrations.create',
        'store' => 'sansay.registrations.store',
        'show' => 'sansay.registrations.show',
        'edit' => 'sansay.registrations.edit',
        'update' => 'sansay.registrations.update',
        'destroy' => 'sansay.registrations.destroy',
    ]);

    Route::post('sansay/registrations/select-all', [SansayRegistrationsController::class, 'selectAll'])->name('sansay.registrations.select.all');
    Route::post('sansay/registrations/delete', [SansayRegistrationsController::class, 'destroy'])->name('sansay.registrations.delete');

    // Sansay Active Calls
    Route::resource('sansay/active-calls', SansayActiveCallsController::class)->names([
        'index' => 'sansay.active-calls.index',
        'create' => 'sansay.active-calls.create',
        'store' => 'sansay.active-calls.store',
        'show' => 'sansay.active-calls.show',
        'edit' => 'sansay.active-calls.edit',
        'update' => 'sansay.active-calls.update',
        'destroy' => 'sansay.active-calls.destroy',
    ]);

    Route::post('sansay/active-calls/select-all', [SansayActiveCallsController::class, 'selectAll'])->name('sansay.active-calls.select.all');
    Route::post('sansay/active-calls/delete', [SansayActiveCallsController::class, 'destroy'])->name('sansay.active-calls.delete');

    // Active Calls
    Route::resource('active-calls', ActiveCallsController::class);
    Route::post('/active-calls/select-all', [ActiveCallsController::class, 'selectAll'])->name('active-calls.select.all');
    Route::post('/active-calls/action', [ActiveCallsController::class, 'handleAction'])->name('active-calls.action');

    // Pro Features
    Route::resource('pro-features', ProFeaturesController::class);
    // Route::post('/pro-features/action', [ProFeaturesController::class, 'handleAction'])->name('pro-features.action');
    Route::post('pro-features/item-options', [ProFeaturesController::class, 'getItemOptions'])->name('pro-features.item.options');
    Route::post('pro-features/activate', [ProFeaturesController::class, 'activate'])->name('pro-features.activate');
    Route::post('pro-features/install', [ProFeaturesController::class, 'install'])->name('pro-features.install');
    Route::post('pro-features/uninstall', [ProFeaturesController::class, 'uninstall'])->name('pro-features.uninstall');


    // Whitelisted Numbers
    Route::resource('whitelisted-numbers', WhitelistedNumbersController::class);
    Route::post('/whitelisted-numbers/bulk-delete', [WhitelistedNumbersController::class, 'bulkDelete'])->name('whitelisted-numbers.bulk.delete');
    Route::post('/whitelisted-numbers/select-all', [WhitelistedNumbersController::class, 'selectAll'])->name('whitelisted-numbers.select.all');



});


// Route::group(['prefix' => '/'], function () {
//     Route::get('', [RoutingController::class, 'index'])->name('root');
//     Route::get('{first}/{second}/{third}', [RoutingController::class, 'thirdLevel'])->name('third');
//     Route::get('{first}/{second}', [RoutingController::class, 'secondLevel'])->name('second');
//     Route::get('/any/{any}', [RoutingController::class, 'root'])->name('any');
// });

// Auth::routes();

//Route::get('/dashboard', [App\Http\Controllers\HomeController::class, 'index'])->name('dashboard');
