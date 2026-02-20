<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AppsController;
use App\Http\Controllers\CdrsController;
use App\Http\Controllers\LogsController;
use App\Http\Controllers\FaxesController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\GroupsController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ContactsController;
use App\Http\Controllers\FaxInboxController;
use App\Http\Controllers\FaxQueueController;
use App\Http\Controllers\FirewallController;
use App\Http\Controllers\MessagesController;
use App\Http\Controllers\UserLogsController;
use App\Http\Controllers\CsrfTokenController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GreetingsController;
use App\Http\Controllers\VoicemailController;
use App\Http\Controllers\EmailQueueController;
use App\Http\Controllers\ExtensionsController;
use App\Http\Controllers\PolycomLogController;
use App\Http\Controllers\RecordingsController;
use App\Http\Controllers\RingGroupsController;
use App\Http\Controllers\ActiveCallsController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ProFeaturesController;
use App\Http\Controllers\WakeupCallsController;
use App\Http\Controllers\DomainGroupsController;
use App\Http\Controllers\PhoneNumbersController;
use App\Http\Controllers\ProvisioningController;
use App\Http\Controllers\BusinessHoursController;
use App\Http\Controllers\CallRecordingController;
use App\Http\Controllers\RegistrationsController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\AppsCredentialsController;
use App\Http\Controllers\MessageSettingsController;
use App\Http\Controllers\SansayActiveCallsController;
use App\Http\Controllers\VoicemailMessagesController;
use App\Http\Controllers\CallRoutingOptionsController;
use App\Http\Controllers\WhitelistedNumbersController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\ExtensionStatisticsController;
use App\Http\Controllers\SansayRegistrationsController;
use App\Http\Controllers\VirtualReceptionistController;
use App\Http\Controllers\DeviceCloudProvisioningController;

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
Route::webhooks('webhook/mailgun', 'mailgun');
Route::webhooks('webhook/commio/sms', 'commio_messaging');
Route::webhooks('webhook/sinch/sms', 'sinch_messaging');
Route::webhooks('webhook/bandwidth/sms', 'bandwidth_messaging');
Route::webhooks('webhook/telnyx/sms', 'telnyx_messaging');
Route::webhooks('webhook/clicksend/sms', 'clicksend_messaging');
Route::webhooks('/sms/ringotelwebhook', 'ringotel_messaging');
Route::webhooks('/webhook/freeswitch', 'freeswitch');
Route::webhooks('/webhook/stripe', 'stripe');
Route::webhooks('/webhook/assemblyai', 'assemblyai');

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

Route::match(['GET', 'HEAD'], '/prov/{path}', [ProvisioningController::class, 'serve'])
    ->where('path', '.*')
    ->middleware(['throttle:provision', 'provision.digest'])
    ->name('provision.serve');

// Call Recordings
Route::get('/call-detail-records/recordings/{uuid}/stream', [CallRecordingController::class, 'stream'])->name('cdrs.recording.stream');
Route::get('/call-detail-records/recordings/{uuid}/download', [CallRecordingController::class, 'download'])->name('cdrs.recording.download');

Route::group(['middleware' => 'auth'], function () {

    // Extensions
    Route::get('extensions', [ExtensionsController::class, 'index'])->name('extensions.index');
    Route::post('extensions/duplicate', [ExtensionsController::class, 'duplicate'])->name('extensions.duplicate');

    // Route::resource('extensions', ExtensionsController::class);
    Route::post('/extensions/{extension}/assign-device', [ExtensionsController::class, 'assignDevice'])->name('extensions.assign-device');
    Route::post('/extensions/{extension}/device', [ExtensionsController::class, 'oldStoreDevice'])->name('extensions.store-device');
    Route::get('/extensions/{extension}/device/{device}/edit', [ExtensionsController::class, 'oldEditDevice'])->name('extensions.edit-device');
    Route::put('/extensions/{extension}/device/{device}', [ExtensionsController::class, 'oldUpdateDevice'])->name('extensions.update-device');
    Route::delete('/extensions/{extension}/unassign/{deviceLine}/device', [ExtensionsController::class, 'unAssignDevice'])->name('extensions.unassign-device');
    Route::delete('/extensions/{extension}/callforward/{type}', [ExtensionsController::class, 'clearCallforwardDestination'])->name('extensions.clear-callforward-destination');
    Route::post('/extensions/{extension}/send-event-notify', [ExtensionsController::class, 'sendEventNotify'])->name('extensions.send-event-notify');
    Route::post('/extensions/send-event-notify-all', [ExtensionsController::class, 'sendEventNotifyAll'])->name('extensions.send-event-notify-all');
    Route::get('/extensions-export', [ExtensionsController::class, 'export'])->name('extensions.export');

    // Call Detail Records
    Route::get('/call-detail-records', [CdrsController::class, 'index'])->name('cdrs.index');
    Route::post('/call-detail-records', [CdrsController::class, 'index'])->name('cdrs.download');
    Route::post('/call-detail-records/export', [CdrsController::class, 'export'])->name('cdrs.export');

    //Extension Statistics
    Route::get('/extension-statistics', [ExtensionStatisticsController::class, 'index'])->name('extension-statistics.index');

    //Domains
    Route::get('domains/extensions', [DomainController::class, 'countExtensionsInDomains']);
    Route::post('/domains/switch', [DomainController::class, 'switchDomain'])->name('switchDomain');
    Route::get('/domains/switch', function () {
        return redirect('/dashboard');
    });
    Route::get('/domains/switch/{domain}', [DomainController::class, 'switchDomainFusionPBX'])->name('switchDomainFusionPBX');
    Route::get('/domains/filter/', [DomainController::class, 'filterDomainsFusionPBX'])->name('filterDomainsFusionPBX');

    //Users
    Route::get('users', [UsersController::class, 'index'])->name('users.index');
    // Route::post('user/{user}/settings', [UserSettingsController::class, 'store'])->name('users.settings.store');
    // Route::delete('user/settings/{setting}', [UserSettingsController::class, 'destroy'])->name('users.settings.destroy');
    Route::post('user/password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('users.password.email');

    // Groups
    Route::get('groups', [GroupsController::class, 'index'])->name('groups.index');

    // Domains
    Route::get('domains', [DomainController::class, 'index'])->name('domains.index');

    //Fax
    Route::get('faxes', [FaxesController::class, 'index'])->name('faxes.index');
    Route::get('/fax/{fax}/inbox', [FaxInboxController::class, 'index'])->name('fax-inbox.index');
    Route::get('/faxes/sent/{id}', [FaxesController::class, 'sent'])->name('faxes.sent.list');
    Route::get('/faxes/active/{id}', [FaxesController::class, 'active'])->name('faxes.active.list');
    Route::get('/faxes/log/{id}', [FaxesController::class, 'log'])->name('faxes.log.list');

    // Domain Groups
    Route::get('domain-groups', [DomainGroupsController::class, 'index'])->name('domain-groups.index');
    Route::post('ring-groups/duplicate', [RingGroupsController::class, 'duplicate'])->name('ring-groups.duplicate');

    // Ring Groups
    Route::get('ring-groups', [RingGroupsController::class, 'index'])->name('ring-groups.index');

    // User Logs
    Route::get('user-logs', [UserLogsController::class, 'index'])->name('user-logs.index');

    // Business hours
    Route::get('business-hours', [BusinessHoursController::class, 'index'])->name('business-hours.index');

    //Voicemails
    Route::get('voicemails', [VoicemailController::class, 'index'])->name('voicemails.index');

    // Voicemail Messages
    Route::get('/voicemails/{voicemail}/messages/', [VoicemailMessagesController::class, 'index'])->name('voicemails.messages.index');
    Route::delete('/voicemails/messages/{message}', [VoicemailMessagesController::class, 'destroy'])->name('voicemails.messages.destroy');
    Route::get('/voicemails/messages/{message}', [VoicemailMessagesController::class, 'getVoicemailMessage'])->name('voicemail.message');
    Route::post('/voicemails/messages/get-url', [VoicemailMessagesController::class, 'getVoicemailMessageUrl'])->name('voicemail.message.url');
    Route::get('/voicemails/messages/{message}/download', [VoicemailMessagesController::class, 'downloadVoicemailMessage'])->name('downloadVoicemailMessage');
    Route::get('/voicemails/messages/{message}/delete', [VoicemailMessagesController::class, 'deleteVoicemailMessage'])->name('deleteVoicemailMessage');
    Route::post('/voicemails/messages/bulk-delete', [VoicemailMessagesController::class, 'bulkDelete'])->name('voicemails.messages.bulk.delete');
    Route::post('/voicemails/messages/select-all', [VoicemailMessagesController::class, 'selectAll'])->name('voicemails.messages.select.all');

    // Virtual Receptionist
    Route::resource('virtual-receptionists', VirtualReceptionistController::class);
    Route::post('virtual-receptionists/item-options', [VirtualReceptionistController::class, 'getItemOptions'])->name('virtual-receptionists.item.options');
    Route::post('/virtual-receptionists/bulk-delete', [VirtualReceptionistController::class, 'bulkDelete'])->name('virtual-receptionists.bulk.delete');
    Route::post('/virtual-receptionists/select-all', [VirtualReceptionistController::class, 'selectAll'])->name('virtual-receptionists.select.all');
    Route::post('/virtual-receptionists/{virtual_receptionist}/greeting', [VirtualReceptionistController::class, 'getVirtualReceptionistGreeting'])->name('virtual-receptionist.greeting');
    Route::post('/virtual-receptionists/greeting/apply', [VirtualReceptionistController::class, 'applyGreeting'])->name('virtual-receptionist.greeting.apply');
    Route::post('/virtual-receptionists/key/create', [VirtualReceptionistController::class, 'createKey'])->name('virtual-receptionist.key.create');
    Route::put('/virtual-receptionists/key/update', [VirtualReceptionistController::class, 'updateKey'])->name('virtual-receptionist.key.update');
    Route::post('/virtual-receptionists/key/delete', [VirtualReceptionistController::class, 'destroyKey'])->name('virtual-receptionist.key.destroy');

    // Account settings
    Route::get('account-settings', [AccountSettingsController::class, 'index'])->name('account-settings.index');

    // Logs
    Route::get('logs', [LogsController::class, 'index'])->name('logs.index');

    // System Settings
    Route::get('system-settings', [SystemSettingsController::class, 'index'])->name('system-settings.index');

    // Greetings
    Route::post('/greetings/url', [GreetingsController::class, 'getGreetingUrl'])->name('greeting.url');
    Route::get('/greetings/serve/{file_name}', [GreetingsController::class, 'serveGreetingFile'])->name('greeting.file.serve');
    Route::post('/greetings/text-to-speech', [GreetingsController::class, 'textToSpeech'])->name('greetings.textToSpeech');
    Route::post('/greetings/apply', [GreetingsController::class, 'applyAIGreetingFile'])->name('greeting.file.apply');
    Route::post('greetings/delete-greeting', [GreetingsController::class, 'deleteGreetingFile'])->name('greetings.file.delete');
    Route::post('greetings/update-greeting', [GreetingsController::class, 'updateGreetingFile'])->name('greetings.file.update');
    Route::post('greetings/upload-greeting', [GreetingsController::class, 'uploadGreeting'])->name('greetings.file.upload');
    Route::post('/ivr/message/url', [GreetingsController::class, 'getIvrMessageUrl'])->name('ivr.message.url');
    Route::get('/ivr/message/serve/{file_name}', [GreetingsController::class, 'serveIvrMessageFile'])
        ->name('ivr.message.file.serve')
        ->where('file_name', '(.*)');

    // Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
    Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
    Route::get('/logout', [App\Http\Controllers\Auth\LoginController::class, 'logout']);

    //Devices
    Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::post('devices/duplicate', [DeviceController::class, 'duplicate'])->name('devices.duplicate');

    //Phone Numbers
    Route::get('phone-numbers', [PhoneNumbersController::class, 'index'])->name('phone-numbers.index');
    Route::get('/phone-numbers-export', [PhoneNumbersController::class, 'export'])->name('phone-numbers.export');
    Route::post('/phone-numbers/import', [PhoneNumbersController::class, 'importPreview'])->name('phone-numbers.import');
    Route::post('/phone-numbers/import/commit', [PhoneNumbersController::class, 'importCommit'])->name('phone-numbers.import.commit');

    //Wakeup Calls
    Route::resource('wakeup-calls', WakeupCallsController::class);
    Route::post('/wakeup-calls/select-all', [WakeupCallsController::class, 'selectAll'])->name('wakeup-calls.select.all');
    // Route::post('/wakeup-calls/bulk-update', [WakeupCallsController::class, 'bulkUpdate'])->name('wakeup-calls.bulk.update');
    Route::post('/wakeup-calls/bulk-delete', [WakeupCallsController::class, 'bulkDelete'])->name('wakeup-calls.bulk.delete');
    Route::post('wakeup-calls/item-options', [WakeupCallsController::class, 'getItemOptions'])->name('wakeup-calls.item.options');
    Route::post('wakeup-calls/settings', [WakeupCallsController::class, 'getSettings'])->name('wakeup-calls.settings');
    Route::put('wakeup-calls/settings/update', [WakeupCallsController::class, 'updateSettings'])->name('wakeup-calls.settings.update');


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
    Route::post('/apps/organization/all', [AppsController::class, 'getOrganizations'])->name('apps.organization.all');
    Route::post('/apps/organization/pair', [AppsController::class, 'pairOrganization'])->name('apps.organization.pair');
    Route::post('/apps/mobile-app-options', [AppsController::class, 'getMobileAppOptions'])->name('apps.user.options');
    //Route::get('/apps/organization/update', [AppsController::class, 'updateOrganization']) ->name('appsUpdateOrganization');
    Route::post('/apps/connection/create', [AppsController::class, 'createConnection'])->name('apps.connection.create');
    Route::put('/apps/connection/update', [AppsController::class, 'updateConnection'])->name('apps.connection.update');
    Route::post('/apps/connection/delete', [AppsController::class, 'destroyConnection'])->name('apps.connection.destroy');
    Route::get('/apps/connection/update', [AppsController::class, 'updateConnection'])->name('appsUpdateConnection');
    Route::post('/apps/token/get', [AppsController::class, 'getToken'])->name('apps.token.get');
    Route::post('/apps/token/update', [AppsController::class, 'updateToken'])->name('apps.token.update');
    Route::post('/apps/user/create', [AppsController::class, 'createUser'])->name('apps.user.create');
    Route::post('/apps/user/delete', [AppsController::class, 'deleteUser'])->name('apps.user.delete');
    Route::post('/apps/user/activate', [AppsController::class, 'activateUser'])->name('apps.user.activate');
    Route::post('/apps/user/deactivate', [AppsController::class, 'deactivateUser'])->name('apps.user.deactivate');
    Route::post('/apps/sync-users', [AppsController::class, 'syncUsers'])->name('apps.users.sync');
    Route::post('/apps/user/reset-password', [AppsController::class, 'resetPassword'])->name('apps.user.reset');
    Route::post('/apps/users/{extension}/status', [AppsController::class, 'SetStatus'])->name('appsSetStatus');
    Route::get('/apps/email', [AppsController::class, 'emailUser'])->name('emailUser');

    // Contacts
    Route::resource('contacts', ContactsController::class);
    Route::post('/contacts/item-options', [ContactsController::class, 'getItemOptions'])->name('contacts.item.options');
    Route::post('/contacts/bulk-delete', [ContactsController::class, 'bulkDelete'])->name('contacts.bulk.delete');
    Route::post('/contacts/select-all', [ContactsController::class, 'selectAll'])->name('contacts.select.all');
    Route::post('/contacts/import', [ContactsController::class, 'import'])->name('contacts.import');
    Route::get('/contacts/template/download', [ContactsController::class, 'downloadTemplate'])->name('contacts.download.template');
    Route::get('/contacts-export', [ContactsController::class, 'export'])->name('contacts.export');

    // SMS for testing
    // Route::get('/sms/ringotelwebhook', [SmsWebhookController::class,"messageFromRingotel"]);

    // Messages
    // Route::resource('messages', MessagesController::class);
    Route::get('/messages', [MessagesController::class, 'index'])->name('messages.index');
    // Route::post('/messages/retry', [MessagesController::class, 'retry'])->name('messages.retry');
    // Route::post('/messages/bulk-update', [DeviceController::class, 'bulkUpdate'])->name('messages.bulk.update');
    // Route::post('/messages/bulk-delete', [DeviceController::class, 'bulkDelete'])->name('messages.bulk.delete');
    // Route::post('/messages/select-all', [DeviceController::class, 'selectAll'])->name('messages.select.all');



    // Message Settings
    Route::get('/message-settings', [MessageSettingsController::class, 'index'])->name('messages.settings');


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
    Route::resource('faxqueue', FaxQueueController::class);
    Route::post('/faxqueue/retry', [FaxQueueController::class, 'retry'])->name('faxqueue.retry');
    Route::post('/faxqueue/select-all', [FaxQueueController::class, 'selectAll'])->name('faxqueue.select.all');

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


    // Cloud Provisioning
    //Route::resource('cloud-provisioning', DeviceCloudProvisioningController::class);
    Route::post('/cloud-provisioning/domains', [DeviceCloudProvisioningController::class, 'getAvailableDomains'])->name('cloud-provisioning.domains');
    // Route::post('/cloud-provisioning/select-all', [ActiveCallsController::class, 'selectAll'])->name('active-calls.select.all');
    // Route::post('/cloud-provisioning/action', [ActiveCallsController::class, 'handleAction'])->name('active-calls.action');
    Route::post('/cloud-provisioning/sync-devices', [DeviceCloudProvisioningController::class, 'syncDevices'])->name('cloud-provisioning.devices.sync');
    Route::post('/cloud-provisioning/register', [DeviceCloudProvisioningController::class, 'register'])->name('cloud-provisioning.register');
    Route::post('/cloud-provisioning/deregister', [DeviceCloudProvisioningController::class, 'deregister'])->name('cloud-provisioning.deregister');
    //Route::post('/cloud-provisioning/devices/organizations', [DeviceCloudProvisioningController::class, 'devicesOrganizations'])->name('cloudProvisioning.devices.organizations');
    //Route::post('/cloud-provisioning/devices/organizations', [DeviceCloudProvisioningController::class, 'devicesOrganizations'])->name('cloudProvisioning.devices.organizations');
    //Route::post('/cloud-provisioning/devices/organizations', [DeviceCloudProvisioningController::class, 'devicesOrganizations'])->name('cloudProvisioning.devices.organizations');


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
