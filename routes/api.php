<?php

use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\AccessControlController;
use App\Http\Controllers\Api\EmergencyCallController;
use App\Http\Controllers\Api\HolidayHoursController;
use App\Http\Controllers\Api\LocationsController;
use App\Http\Controllers\Api\ProvisioningTemplateController;
use App\Http\Controllers\BusinessHoursController;
use App\Http\Controllers\CallFlowController;
use App\Http\Controllers\CallTranscriptionController;
use App\Http\Controllers\CdrsController;
use App\Http\Controllers\CharPmsWebhookController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DeviceCloudProvisioningController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\DomainGroupsController;
use App\Http\Controllers\EmailLogsController;
use App\Http\Controllers\EmailQueueController;
use App\Http\Controllers\ExtensionsController;
use App\Http\Controllers\ExtensionStatisticsController;
use App\Http\Controllers\FaxesController;
use App\Http\Controllers\FaxInboxController;
use App\Http\Controllers\FaxLogController;
use App\Http\Controllers\FaxSentController;
use App\Http\Controllers\GreetingsController;
use App\Http\Controllers\GatewayController;
use App\Http\Controllers\GroupsController;
use App\Http\Controllers\HotelHousekeepingDefinitionController;
use App\Http\Controllers\HotelRoomController;
use App\Http\Controllers\HotelRoomStatusController;
use App\Http\Controllers\InboundWebhooksController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MessageSettingsController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\PhoneNumbersController;
use App\Http\Controllers\RecordingsManagerController;
use App\Http\Controllers\RingGroupsController;
use App\Http\Controllers\SipStatusController;
use App\Http\Controllers\SpeedDialController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\UserLogsController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\VirtualReceptionistController;
use App\Http\Controllers\VoicemailController;
use App\Http\Controllers\VoicemailMessagesController;
use Illuminate\Support\Facades\Route;

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

Route::group(['middleware' => ['auth:sanctum', 'api.cookie.auth']], function () {
    // Tokens
    Route::resource('/tokens', TokenController::class);
    Route::post('tokens/bulk-delete', [TokenController::class, 'bulkDelete'])->name('tokens.bulk.delete');

    // Locations
    Route::resource('/locations', LocationsController::class);
    Route::post('locations/bulk-delete', [LocationsController::class, 'bulkDelete'])->name('locations.bulk.delete');

    // Provisioning Templates
    Route::resource('/provisioning-templates', ProvisioningTemplateController::class);
    Route::post('provisioning-templates/bulk-delete', [ProvisioningTemplateController::class, 'bulkDelete'])->name('provisioning-templates.bulk.delete');
    Route::post('/provisioning-templates/item-options', [ProvisioningTemplateController::class, 'getItemOptions'])->name('provisioning-templates.item.options');
    Route::post('/provisioning-templates/content', [ProvisioningTemplateController::class, 'getTemplateContent'])->name('provisioning-templates.content');

    // Email logs
    Route::resource('/email-logs', EmailLogsController::class);
    Route::post('/email-logs/retry', [EmailLogsController::class, 'retry'])->name('email-logs.retry');

    // Inbound Webhooks
    Route::resource('/inbound-webhooks', InboundWebhooksController::class);

    // Hotel rooms
    Route::resource('/hotel-rooms', HotelRoomController::class);
    Route::post('/hotel-rooms/item-options', [HotelRoomController::class, 'getItemOptions'])->name('hotel-rooms.item.options');
    Route::post('hotel-rooms/bulk-delete', [HotelRoomController::class, 'bulkDelete'])->name('hotel-rooms.bulk.delete');
    Route::post('hotel-rooms/bulk-store', [HotelRoomController::class, 'bulkStore'])->name('hotel-rooms.bulk.store');

    // Hotel room status
    Route::resource('/hotel-room-status', HotelRoomStatusController::class);
    Route::post('/hotel-room-status/item-options', [HotelRoomStatusController::class, 'getItemOptions'])->name('hotel-room-status.item.options');
    Route::post('hotel-room-status/bulk-delete', [HotelRoomStatusController::class, 'bulkDelete'])->name('hotel-room-status.bulk.delete');

    // Hotel housekeeping
    Route::resource('/housekeeping', HotelHousekeepingDefinitionController::class);
    Route::post('/housekeeping/item-options', [HotelHousekeepingDefinitionController::class, 'getItemOptions'])->name('housekeeping.item.options');
    Route::post('/housekeeping/default-codes', [HotelHousekeepingDefinitionController::class, 'defaultCodes'])->name('housekeeping.default-codes');


    // Emergency calls
    Route::resource('/emergency-calls', EmergencyCallController::class);
    Route::post('/emergency-calls/item-options', [EmergencyCallController::class, 'getItemOptions'])->name('emergency-calls.item.options');
    Route::post('/emergency-calls/bulk-delete', [EmergencyCallController::class, 'bulkDelete'])->name('emergency-calls.bulk.delete');
    Route::post('/emergency-calls/check-service-status', [EmergencyCallController::class, 'checkServiceStatus'])->name('emergency-calls.check.service.status');


    // Ring Groups
    Route::post('ring-groups', [RingGroupsController::class, 'store'])->name('ring-groups.store');
    Route::get('ring-groups/data', [RingGroupsController::class, 'getData'])->name('ring-groups.data');
    Route::put('ring-groups/{ring_group}', [RingGroupsController::class, 'update'])->name('ring-groups.update');
    Route::delete('ring-groups/{ring_group}', [RingGroupsController::class, 'destroy'])->name('ring-groups.destroy');
    Route::post('ring-groups/item-options', [RingGroupsController::class, 'getItemOptions'])->name('ring-groups.item.options');
    Route::post('ring-groups/bulk-delete', [RingGroupsController::class, 'bulkDelete'])->name('ring-groups.bulk.delete');
    Route::post('ring-groups/select-all', [RingGroupsController::class, 'selectAll'])->name('ring-groups.select.all');
    Route::post('ring-groups/duplicate', [RingGroupsController::class, 'duplicate'])->name('ring-groups.duplicate');
    Route::post('ring-groups/{ring_group}/get-greeting', [RingGroupsController::class, 'getGreeting'])->name('ring-groups.getGreeting');
    Route::post('ring-groups/{ring_group}/upload-greeting', [RingGroupsController::class, 'uploadGreeting'])->name('ring-groups.uploadGreeting');
    Route::post('ring-groups/{ring_group}/delete-greeting', [RingGroupsController::class, 'deleteGreeting'])->name('ring-groups.deleteGreeting');

    // Recordings Manager
    Route::get('recordings-manager/data', [RecordingsManagerController::class, 'getData'])->name('recordings-manager.data');
    Route::post('recordings-manager/bulk-delete', [RecordingsManagerController::class, 'bulkDelete'])->name('recordings-manager.bulk.delete');
    Route::post('recordings-manager/select-all', [RecordingsManagerController::class, 'selectAll'])->name('recordings-manager.select.all');

    // SIP Status
    Route::get('sip-status/data', [SipStatusController::class, 'data'])->name('sip-status.data');
    Route::post('sip-status/action', [SipStatusController::class, 'action'])->name('sip-status.action');

    // Ring Group AI Text-to-Speech & File Serving
    Route::post('ring-groups/{ring_group}/text-to-speech', [RingGroupsController::class, 'textToSpeech'])->name('ring-groups.textToSpeech');
    Route::post('ring-groups/apply-greeting', [RingGroupsController::class, 'applyGreetingFile'])->name('ring-groups.applyGreeting');
    // Route::get('ring-groups/serve-greeting/{domain}/{file}', [RingGroupsController::class, 'serveGreetingFile'])->name('ring-groups.serveGreeting');

    // Greetings
    Route::get('greetings', [GreetingsController::class, 'greetings'])->name('greetings.greetings');
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

    // Business Hours
    Route::post('business-hours', [BusinessHoursController::class, 'store'])->name('business-hours.store');
    Route::put('business-hours/{business_hour}', [BusinessHoursController::class, 'update'])->name('business-hours.update');
    Route::post('business-hours/item-options', [BusinessHoursController::class, 'getItemOptions'])->name('business-hours.item.options');
    Route::post('business-hours/bulk-delete', [BusinessHoursController::class, 'bulkDelete'])->name('business-hours.bulk.delete');
    Route::post('business-hours/select-all', [BusinessHoursController::class, 'selectAll'])->name('business-hours.select.all');
    Route::post('business-hours/duplicate', [BusinessHoursController::class, 'duplicate'])->name('business-hours.duplicate');

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
    Route::get('extensions/data', [ExtensionsController::class, 'getData'])->name('extensions.data');
    Route::post('extensions/item-options', [ExtensionsController::class, 'getItemOptions'])->name('extensions.item.options');
    Route::post('extensions/bulk-update', [ExtensionsController::class, 'bulkUpdate'])->name('extensions.bulk.update');
    Route::post('extensions/bulk-delete', [ExtensionsController::class, 'bulkDelete'])->name('extensions.bulk.delete');
    Route::post('extensions/select-all', [ExtensionsController::class, 'selectAll'])->name('extensions.select.all');
    Route::get('/extensions/registrations', [ExtensionsController::class, 'registrations'])->name('extensions.registrations');
    Route::get('/extensions/{extension}/devices', [ExtensionsController::class, 'devices'])->name('extensions.devices');
    Route::get('/extensions/{extension}/sip-credentials', [ExtensionsController::class, 'sipCredentials'])->name('extensions.sip.credentials');
    Route::get('/extensions/{extension}/regenerate-sip-credentials', [ExtensionsController::class, 'regenerateSipCredentials'])->name('extensions.sip.credentials.regenerate');
    Route::get('/extensions/template/download', [ExtensionsController::class, 'downloadTemplate'])->name('extensions.template.download');
    Route::post('/extensions/import', [ExtensionsController::class, 'import'])->name('extensions.import');
    Route::post('/extensions/make-user', [ExtensionsController::class, 'makeUser'])->name('extensions.make.user');
    Route::post('/extensions/password', [ExtensionsController::class, 'updatePassword'])->name('extensions.password.update');

    // Extension statistics
    //Route::get('/extension-statistics/data', [ExtensionStatisticsController::class, 'getData'])->name('extension-statistics.data');

    // Voicemails
    Route::post('voicemails', [VoicemailController::class, 'store'])->name('voicemails.store');
    Route::put('voicemails/{voicemail}', [VoicemailController::class, 'update'])->name('voicemails.update');
    Route::get('voicemails/data', [VoicemailController::class, 'getData'])->name('voicemails.data');
    Route::post('voicemails/item-options', [VoicemailController::class, 'getItemOptions'])->name('voicemails.item.options');
    Route::post('/voicemails/bulk-delete', [VoicemailController::class, 'bulkDelete'])->name('voicemails.bulk.delete');
    Route::post('/voicemails/select-all', [VoicemailController::class, 'selectAll'])->name('voicemails.select.all');
    Route::post('/voicemails/{voicemail}/text-to-speech', [VoicemailController::class, 'textToSpeech'])->name('voicemails.textToSpeech');
    Route::post('/voicemails/{voicemail}/text-to-speech-for-name', [VoicemailController::class, 'textToSpeechForName'])->name('voicemails.textToSpeechForName');
    Route::get('/voicemail/{domain}/{voicemail_id}/{file}', [VoicemailController::class, 'serveVoicemailFile'])->name('voicemail.file.serve');
    Route::get('/voicemails/{voicemail}/greetings', [VoicemailController::class, 'getGreetings'])->name('voicemails.getGreetings');
    Route::post('/voicemail/apply-greeting', [VoicemailController::class, 'applyVoicemailFile'])->name('voicemail.file.apply');
    Route::post('/voicemail/{domain}/{voicemail}/{file}/name', [VoicemailController::class, 'applyVoicemailFileForName'])->name('voicemail.file.name.apply');
    Route::post('/voicemail/{voicemail}/greeting', [VoicemailController::class, 'getVoicemailGreeting'])->name('voicemail.greeting');
    Route::post('voicemails/{voicemail}/delete-greeting', [VoicemailController::class, 'deleteGreeting'])->name('voicemails.deleteGreeting');
    Route::post('voicemails/{voicemail}/upload-greeting', [VoicemailController::class, 'uploadGreeting'])->name('voicemails.uploadGreeting');
    Route::post('/voicemail/{voicemail}/recorded-name', [VoicemailController::class, 'getRecordedName'])->name('voicemail.recorded_name');
    Route::post('voicemails/{voicemail}/delete-recorded-name', [VoicemailController::class, 'deleteRecordedName'])->name('voicemails.deleteRecordedName');
    Route::post('voicemails/{voicemail}/upload-recorded-name', [VoicemailController::class, 'uploadRecordedName'])->name('voicemails.uploadRecordedName');

    // Voicemail Messages
    Route::get('/voicemails/messages/data', [VoicemailMessagesController::class, 'getData'])->name('voicemails.messages.data');
    Route::get('/voicemails/messages/recording', [VoicemailMessagesController::class, 'getRecording'])->name('voicemails.messages.recording');
    Route::get('/voicemails/messages/{uuid}/file', [VoicemailMessagesController::class, 'getFile'])->name('voicemails.messages.file');
    Route::post('/voicemails/messages/get-url', [VoicemailMessagesController::class, 'getVoicemailMessageUrl'])->name('voicemail.message.url');
    Route::post('/voicemails/messages/bulk-delete', [VoicemailMessagesController::class, 'bulkDelete'])->name('voicemails.messages.bulk.delete');
    Route::post('/voicemails/messages/select-all', [VoicemailMessagesController::class, 'selectAll'])->name('voicemails.messages.select.all');
    Route::post('/voicemails/messages/status', [VoicemailMessagesController::class, 'updateStatus'])->name('voicemails.messages.update-status');
    Route::get('/voicemails/messages/{message}', [VoicemailMessagesController::class, 'getVoicemailMessage'])->name('voicemail.message');
    Route::delete('/voicemails/messages/{message}', [VoicemailMessagesController::class, 'destroy'])->name('voicemails.messages.destroy');
    Route::get('/voicemails/messages/{message}/download', [VoicemailMessagesController::class, 'downloadVoicemailMessage'])->name('downloadVoicemailMessage');
    Route::get('/voicemails/messages/{message}/delete', [VoicemailMessagesController::class, 'deleteVoicemailMessage'])->name('deleteVoicemailMessage');

    // Virtual Receptionist
    Route::post('virtual-receptionists', [VirtualReceptionistController::class, 'store'])->name('virtual-receptionists.store');
    Route::put('virtual-receptionists/{virtual_receptionist}', [VirtualReceptionistController::class, 'update'])->name('virtual-receptionists.update');
    Route::get('virtual-receptionists/data', [VirtualReceptionistController::class, 'getData'])->name('virtual-receptionists.data');
    Route::post('virtual-receptionists/item-options', [VirtualReceptionistController::class, 'getItemOptions'])->name('virtual-receptionists.item.options');
    Route::post('/virtual-receptionists/bulk-delete', [VirtualReceptionistController::class, 'bulkDelete'])->name('virtual-receptionists.bulk.delete');
    Route::post('/virtual-receptionists/select-all', [VirtualReceptionistController::class, 'selectAll'])->name('virtual-receptionists.select.all');
    Route::post('/virtual-receptionists/{virtual_receptionist}/greeting', [VirtualReceptionistController::class, 'getVirtualReceptionistGreeting'])->name('virtual-receptionist.greeting');
    Route::post('/virtual-receptionists/greeting/apply', [VirtualReceptionistController::class, 'applyGreeting'])->name('virtual-receptionist.greeting.apply');
    Route::post('/virtual-receptionists/key/create', [VirtualReceptionistController::class, 'createKey'])->name('virtual-receptionist.key.create');
    Route::put('/virtual-receptionists/key/update', [VirtualReceptionistController::class, 'updateKey'])->name('virtual-receptionist.key.update');
    Route::post('/virtual-receptionists/key/delete', [VirtualReceptionistController::class, 'destroyKey'])->name('virtual-receptionist.key.destroy');
    Route::get('/virtual-receptionists/greetings', [VirtualReceptionistController::class, 'getGreetings'])->name('virtual-receptionists.getGreetings');

    // Inbound Webhooks
    Route::get('/inbound-webhooks/data', [InboundWebhooksController::class, 'getData'])->name('inbound-webhooks.data');

    // User logs
    Route::post('user-logs/select-all', [UserLogsController::class, 'selectAll'])->name('user-logs.select.all');

    // Devices 
    Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
    Route::put('devices/{device}', [DeviceController::class, 'update'])->name('devices.update');
    Route::get('/devices/data', [DeviceController::class, 'getData'])->name('devices.data');
    Route::post('/devices/bulk-update', [DeviceController::class, 'bulkUpdate'])->name('devices.bulk.update');
    Route::post('/devices/bulk-delete', [DeviceController::class, 'bulkDelete'])->name('devices.bulk.delete');
    Route::post('/devices/restart', [DeviceController::class, 'restart'])->name('devices.restart');
    Route::post('/devices/select-all', [DeviceController::class, 'selectAll'])->name('devices.select.all');
    Route::post('devices/item-options', [DeviceController::class, 'getItemOptions'])->name('devices.item.options');
    Route::post('devices/assign', [DeviceController::class, 'assign'])->name('devices.assign');
    Route::post('devices/bulk-unassign', [DeviceController::class, 'bulkUnassign'])->name('devices.bulk.unassign');

    // Gateways
    Route::post('gateways', [GatewayController::class, 'store'])->name('gateways.store');
    Route::put('gateways/{gateway}', [GatewayController::class, 'update'])->name('gateways.update');
    Route::get('/gateways/data', [GatewayController::class, 'getData'])->name('gateways.data');
    Route::post('/gateways/item-options', [GatewayController::class, 'getItemOptions'])->name('gateways.item.options');
    Route::post('/gateways/select-all', [GatewayController::class, 'selectAll'])->name('gateways.select.all');
    Route::post('/gateways/bulk-delete', [GatewayController::class, 'bulkDelete'])->name('gateways.bulk.delete');
    Route::post('/gateways/bulk-copy', [GatewayController::class, 'bulkCopy'])->name('gateways.bulk.copy');
    Route::post('/gateways/bulk-toggle', [GatewayController::class, 'bulkToggle'])->name('gateways.bulk.toggle');
    Route::post('/gateways/bulk-start', [GatewayController::class, 'bulkStart'])->name('gateways.bulk.start');
    Route::post('/gateways/bulk-stop', [GatewayController::class, 'bulkStop'])->name('gateways.bulk.stop');

    // Access Controls
    Route::post('access-controls', [AccessControlController::class, 'store'])->name('access-controls.store');
    Route::put('access-controls/{access_control}', [AccessControlController::class, 'update'])->name('access-controls.update');
    Route::get('/access-controls/data', [AccessControlController::class, 'getData'])->name('access-controls.data');
    Route::post('/access-controls/item-options', [AccessControlController::class, 'getItemOptions'])->name('access-controls.item.options');
    Route::post('/access-controls/select-all', [AccessControlController::class, 'selectAll'])->name('access-controls.select.all');
    Route::post('/access-controls/bulk-delete', [AccessControlController::class, 'bulkDelete'])->name('access-controls.bulk.delete');
    Route::post('/access-controls/bulk-copy', [AccessControlController::class, 'bulkCopy'])->name('access-controls.bulk.copy');
    Route::post('/access-controls/reload', [AccessControlController::class, 'reload'])->name('access-controls.reload');

    // Call Flows
    Route::post('call-flows', [CallFlowController::class, 'store'])->name('call-flows.store');
    Route::put('call-flows/{call_flow}', [CallFlowController::class, 'update'])->name('call-flows.update');
    Route::get('/call-flows/data', [CallFlowController::class, 'getData'])->name('call-flows.data');
    Route::post('/call-flows/item-options', [CallFlowController::class, 'getItemOptions'])->name('call-flows.item.options');
    Route::post('/call-flows/groups', [CallFlowController::class, 'storeGroup'])->name('call-flows.groups.store');
    Route::post('/call-flows/select-all', [CallFlowController::class, 'selectAll'])->name('call-flows.select.all');
    Route::post('/call-flows/bulk-delete', [CallFlowController::class, 'bulkDelete'])->name('call-flows.bulk.delete');
    Route::post('/call-flows/bulk-copy', [CallFlowController::class, 'bulkCopy'])->name('call-flows.bulk.copy');
    Route::post('/call-flows/bulk-toggle', [CallFlowController::class, 'bulkToggle'])->name('call-flows.bulk.toggle');

    // Phone Numbers
    Route::post('phone-numbers', [PhoneNumbersController::class, 'store'])->name('phone-numbers.store');
    Route::put('phone-numbers/{phone_number}', [PhoneNumbersController::class, 'update'])->name('phone-numbers.update');
    Route::get('/phone-numbers/data', [PhoneNumbersController::class, 'getData'])->name('phone-numbers.data');
    Route::post('/phone-numbers/select-all', [PhoneNumbersController::class, 'selectAll'])->name('phone-numbers.select.all');
    Route::post('/phone-numbers/bulk-update', [PhoneNumbersController::class, 'bulkUpdate'])->name('phone-numbers.bulk.update');
    Route::post('/phone-numbers/bulk-delete', [PhoneNumbersController::class, 'bulkDelete'])->name('phone-numbers.bulk.delete');
    Route::post('phone-numbers/item-options', [PhoneNumbersController::class, 'getItemOptions'])->name('phone-numbers.item.options');
    Route::get('/phone-numbers/template/download', [PhoneNumbersController::class, 'downloadTemplate'])->name('phone-numbers.template.download');
    //    Route::post('/phone-numbers/import', [PhoneNumbersController::class, 'import'])->name('phone-numbers.import');

    //Cloud Provisioning
    Route::get('/cloud-provisioning/{device}/status', [DeviceCloudProvisioningController::class, 'status'])->name('cloud-provisioning.status');
    Route::post('/cloud-provisioning/{device}/reset', [DeviceCloudProvisioningController::class, 'reset'])->name('cloud-provisioning.reset');
    Route::post('/cloud-provisioning/item-options', [DeviceCloudProvisioningController::class, 'getItemOptions'])->name('cloud-provisioning.item.options');
    Route::post('/cloud-provisioning/organization/create', [DeviceCloudProvisioningController::class, 'createOrganization'])->name('cloud-provisioning.organization.create');
    Route::put('/cloud-provisioning/organization/update', [DeviceCloudProvisioningController::class, 'updateOrganization'])->name('cloud-provisioning.organization.update');
    Route::post('/cloud-provisioning/organization/destroy', [DeviceCloudProvisioningController::class, 'destroyOrganization'])->name('cloud-provisioning.organization.destroy');
    Route::post('/cloud-provisioning/organization/pair', [DeviceCloudProvisioningController::class, 'pairOrganization'])->name('cloud-provisioning.organization.pair');
    Route::post('/cloud-provisioning/organization/all', [DeviceCloudProvisioningController::class, 'getOrganizations'])->name('cloud-provisioning.organization.all');
    Route::post('/cloud-provisioning/token/get', [DeviceCloudProvisioningController::class, 'getToken'])->name('cloud-provisioning.token.get');
    Route::post('/cloud-provisioning/token/update', [DeviceCloudProvisioningController::class, 'updateToken'])->name('cloud-provisioning.token.update');

    // Faxes
    Route::post('faxes', [FaxesController::class, 'store'])->name('faxes.store');
    Route::put('faxes/{fax}', [FaxesController::class, 'update'])->name('faxes.update');
    Route::post('faxes/item-options', [FaxesController::class, 'getItemOptions'])->name('faxes.item.options');
    Route::post('faxes/new-fax-options', [FaxesController::class, 'getNewFaxOptions'])->name('faxes.new.fax.options');
    Route::post('/faxes/bulk-delete', [FaxesController::class, 'bulkDelete'])->name('faxes.bulk.delete');
    Route::post('/faxes/bulk-update', [FaxesController::class, 'bulkUpdate'])->name('faxes.bulk.update');
    Route::get('faxes/recent-outbound', [FaxesController::class, 'getRecentOutbound'])->name('faxes.recent-outbound');
    Route::get('faxes/recent-inbound', [FaxesController::class, 'getRecentInbound'])->name('faxes.recent-inbound');
    Route::get('/faxes/newfax/create', [FaxesController::class, 'new'])->name('faxes.newfax');
    Route::delete('/faxes/deleteSentFax/{id}', [FaxesController::class, 'deleteSentFax'])->name('faxes.file.deleteSentFax');
    Route::delete('/faxes/deleteReceivedFax/{id}', [FaxesController::class, 'deleteReceivedFax'])->name('faxes.file.deleteReceivedFax');
    Route::delete('/faxes/deleteFaxLog/{id}', [FaxesController::class, 'deleteFaxLog'])->name('faxes.file.deleteFaxLog');
    Route::get('/fax/inbox/{file}/download', [FaxInboxController::class, 'download'])->name('fax-inbox.fax.download');
    Route::get('/fax/inbox/{file}/preview', [FaxInboxController::class, 'preview'])->name('fax-inbox.preview');
    Route::post('/fax/inbox/bulk-delete', [FaxInboxController::class, 'bulkDelete'])->name('fax-inbox.bulk.delete');
    Route::post('/fax/inbox/select-all', [FaxInboxController::class, 'selectAll'])->name('fax-inbox.select.all');
    Route::get('/fax/inbox/stats', [FaxInboxController::class, 'getStats'])->name('fax-inbox.stats');
    Route::post('/faxes/send', [FaxesController::class, 'sendFax'])->name('faxes.new.fax.send');
    Route::get('/fax/inbox/data', [FaxInboxController::class, 'getData'])->name('fax-inbox.data');
    Route::get('/fax/sent/data', [FaxSentController::class, 'getData'])->name('fax-sent.data');
    Route::get('/fax/sent/stats', [FaxSentController::class, 'getStats'])->name('fax-sent.stats');
    Route::get('/fax/sent/{file}/download', [FaxSentController::class, 'download'])->name('fax-sent.fax.download');
    Route::get('/fax/sent/{file}/preview', [FaxSentController::class, 'preview'])->name('fax-sent.preview');
    Route::post('/fax/sent/bulk-delete', [FaxSentController::class, 'bulkDelete'])->name('fax-sent.bulk.delete');
    Route::post('/fax/sent/select-all', [FaxSentController::class, 'selectAll'])->name('fax-sent.select.all');
    Route::get('/fax/log/data', [FaxLogController::class, 'getData'])->name('fax-logs.data');
    Route::post('/fax/log/bulk-delete', [FaxLogController::class, 'bulkDelete'])->name('fax-logs.bulk.delete');
    Route::post('/fax/log/select-all', [FaxLogController::class, 'selectAll'])->name('fax-logs.select.all');
    // Route::get('/fax/sent/{faxQueue}/{status?}', [FaxesController::class, 'updateStatus'])->name('faxes.file.updateStatus');

    // Call Detail Records
    Route::get('/call-detail-records/data', [CdrsController::class, 'getData'])->name('cdrs.data');
    Route::get('/call-detail-records/entities', [CdrsController::class, 'getEntities'])->name('cdrs.entities');
    Route::post('/call-detail-records/item-options', [CdrsController::class, 'getItemOptions'])->name('cdrs.item.options');
    Route::get('/call-detail-records/recording-options', [CdrsController::class, 'getRecordingOptions'])->name('cdrs.recording.options');
    Route::post('/call-detail-records/recordings/transcribe', [CallTranscriptionController::class, 'transcribe'])->name('cdrs.recording.transcribe');
    Route::post('/call-detail-records/recordings/summarize', [CallTranscriptionController::class, 'summarize'])->name('cdrs.recording.summarize');

    // Account Settings
    Route::put('account-settings/update', [AccountSettingsController::class, 'update'])->name('account-settings.update');

    // Contacts
    Route::post('contacts', [ContactController::class, 'store'])->name('contacts.store');
    Route::get('contacts/{phoneNumber}', [ContactController::class, 'show'])->name('contacts.show');
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');

    // Route::post('/contacts/item-options', [ContactsController::class, 'getItemOptions'])->name('contacts.item.options');
    // Route::post('/contacts/bulk-delete', [ContactsController::class, 'bulkDelete'])->name('contacts.bulk.delete');
    // Route::post('/contacts/select-all', [ContactsController::class, 'selectAll'])->name('contacts.select.all');
    // Route::post('/contacts/import', [ContactsController::class, 'import'])->name('contacts.import');
    // Route::get('/contacts/template/download', [ContactsController::class, 'downloadTemplate'])->name('contacts.download.template');
    // Route::get('/contacts-export', [ContactsController::class, 'export'])->name('contacts.export');

    // Email Queue
    Route::get('/emailqueue/data', [EmailQueueController::class, 'getData'])->name('emailqueue.data');
    Route::post('/emailqueue/select-all', [EmailQueueController::class, 'selectAll'])->name('emailqueue.select.all');
    Route::post('/emailqueue/bulk-delete', [EmailQueueController::class, 'bulkDelete'])->name('emailqueue.bulk.delete');
    Route::post('/emailqueue/update-status', [EmailQueueController::class, 'updateStatus'])->name('emailqueue.update-status');

    //Organizations
    Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');

    // Speed Dial
    Route::post('speed-dial', [SpeedDialController::class, 'store'])->name('speed-dial.store');
    Route::put('speed-dial/{speed_dial}', [SpeedDialController::class, 'update'])->name('speed-dial.update');
    Route::get('/speed-dial/data', [SpeedDialController::class, 'getData'])->name('speed-dial.data');
    Route::post('/speed-dial/item-options', [SpeedDialController::class, 'getItemOptions'])->name('speed-dial.item.options');
    Route::post('/speed-dial/bulk-delete', [SpeedDialController::class, 'bulkDelete'])->name('speed-dial.bulk.delete');
    Route::post('/speed-dial/select-all', [SpeedDialController::class, 'selectAll'])->name('speed-dial.select.all');
    Route::post('/speed-dial/import', [SpeedDialController::class, 'import'])->name('speed-dial.import');
    Route::get('/speed-dial/template/download', [SpeedDialController::class, 'downloadTemplate'])->name('speed-dial.download.template');
    Route::get('/speed-dial-export', [SpeedDialController::class, 'export'])->name('speed-dial.export');

    // System Settings
    Route::put('system-settings/update', [SystemSettingsController::class, 'update'])->name('system-settings.update');
    Route::get('system-settings/payment_gateways', [SystemSettingsController::class, 'getPaymentGatewayData'])->name('system-settings.payment_gateways');

    // Call Transcription
    Route::get('/call-transcription/providers', [CallTranscriptionController::class, 'getProviders'])->name('call-transcription.providers');
    Route::get('/call-transcription/policy', [CallTranscriptionController::class, 'getPolicy'])->name('call-transcription.policy');
    Route::post('/call-transcription/policy', [CallTranscriptionController::class, 'storePolicy'])->name('call-transcription.policy.store');
    Route::delete('/call-transcription/policy', [CallTranscriptionController::class, 'destroyPolicy'])->name('call-transcription.policy.destroy');
    Route::get('/call-transcription/assemblyai', [CallTranscriptionController::class, 'getAssemblyAiConfig'])->name('call-transcription.assemblyai');
    Route::post('/call-transcription/assemblyai', [CallTranscriptionController::class, 'storeAssemblyAiConfig'])->name('call-transcription.assemblyai.store');
    Route::delete('/call-transcription/assemblyai', [CallTranscriptionController::class, 'destroyAssemblyAiConfig'])->name('call-transcription.assemblyai.destroy');

    // Payment Gateways
    Route::put('/gateways', [PaymentGatewayController::class, 'update'])->name('gateway.update');
    Route::post('/gateways/deactivate', [PaymentGatewayController::class, 'deactivate'])->name('gateway.deactivate');

    // Virtual Receptionist
    Route::post('virtual-receptionists/duplicate', [VirtualReceptionistController::class, 'duplicate'])->name('virtual-receptionists.duplicate');

    // Messages
    Route::get('/messages/rooms', [MessageController::class, 'rooms'])->name('messages.rooms');
    Route::get('/messages/rooms/{roomId}/messages', [MessageController::class, 'roomMessages'])->name('messages.room.messages');
    Route::post('/messages/send', [MessageController::class, 'send'])->name('messages.send');
    Route::get('/messages/logs', [MessageController::class, 'logs'])->name('messages.logs');
    Route::get('/messages/data', [MessageController::class, 'getData'])->name('messages.data');
    Route::post('/messages/mark-read', [MessageController::class, 'markRead'])->name('messages.mark-read');
    Route::post('/messages/retry', [MessageController::class, 'retry'])->name('messages.retry');

    // Message Settings
    Route::get('/message-settings/data', [MessageSettingsController::class, 'getData'])->name('messages.settings.data');
    Route::put('/message-settings/{setting}', [MessageSettingsController::class, 'update'])->name('messages.settings.update');
    Route::post('/message-settings', [MessageSettingsController::class, 'store'])->name('messages.settings.store');
    Route::delete('/message-settings/{setting}', [MessageSettingsController::class, 'destroy'])->name('messages.settings.destroy');
    Route::post('/message-settings/item-options', [MessageSettingsController::class, 'getItemOptions'])->name('messages.settings.item.options');
    Route::post('/message-settings/select-all', [MessageSettingsController::class, 'selectAll'])->name('messages.settings.select.all');
    Route::post('/message-settings/bulk-delete', [MessageSettingsController::class, 'bulkDelete'])->name('messages.settings.bulk.delete');
    Route::post('/message-settings/bulk-update', [MessageSettingsController::class, 'bulkUpdate'])->name('messages.settings.bulk.update');

    // Domains 
    Route::post('domains', [DomainController::class, 'store'])->name('domains.store');
    Route::put('domains/{domain}', [DomainController::class, 'update'])->name('domains.update');
    Route::get('domains/data', [DomainController::class, 'getData'])->name('domains.data');
    Route::post('domains/item-options', [DomainController::class, 'getItemOptions'])->name('domains.item.options');
    Route::post('domains/bulk-delete', [DomainController::class, 'bulkDelete'])->name('domains.bulk.delete');
});

Route::group(['middleware' => ['auth:sanctum']], function () {
    // CHAR PMS
    Route::post('/pms/char', CharPmsWebhookController::class)->name('pms.char');
});
