<?php

namespace App\Models;

use App\Models\IvrMenus;
use App\Models\RingGroups;
use App\Models\DeviceLines;
use App\Models\IvrMenuOptions;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Domain extends Model
{
    use HasApiTokens, HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_domains";

    public $timestamps = false;

    protected $primaryKey = 'domain_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'domain_name',
        'domain_enabled',
        'domain_description'
    ];

    public function getNamedSettingsAttribute(): array
    {
        return $this->settings
            ->mapWithKeys(fn($s) => [
                // use the subcategory as the key
                $s->domain_setting_subcategory => [
                    'value'   => $s->domain_setting_value,
                    'enabled' => (bool) $s->domain_setting_enabled,
                ],
            ])
            ->toArray();
    }

    public function activities()
    {
        return $this->hasMany(Activity::class, 'domain_uuid', 'domain_uuid');
    }

    public function archiveRecordings()
    {
        return $this->hasMany(ArchiveRecording::class, 'domain_uuid', 'domain_uuid');
    }

    public function businessHours()
    {
        return $this->hasMany(BusinessHour::class, 'domain_uuid', 'domain_uuid');
    }

    public function callTranscriptionPolicies()
    {
        return $this->hasMany(CallTranscriptionPolicy::class, 'domain_uuid', 'domain_uuid');
    }

    public function callTranscriptionProviderConfigs()
    {
        return $this->hasMany(CallTranscriptionProviderConfig::class, 'domain_uuid', 'domain_uuid');
    }

    public function callTranscriptions()
    {
        return $this->hasMany(CallTranscription::class, 'domain_uuid', 'domain_uuid');
    }

    public function deviceCloudProvisionings()
    {
        return $this->hasMany(DeviceCloudProvisioning::class, 'domain_uuid', 'domain_uuid');
    }

    public function domainGroupRelations()
    {
        return $this->hasMany(DomainGroupRelations::class, 'domain_uuid', 'domain_uuid');
    }

    public function emailLogs()
    {
        return $this->hasMany(EmailLog::class, 'domain_uuid', 'domain_uuid');
    }

    public function emergencyCallMembers()
    {
        return $this->hasMany(EmergencyCallMember::class, 'domain_uuid', 'domain_uuid');
    }

    public function emergencyCalls()
    {
        return $this->hasMany(EmergencyCall::class, 'domain_uuid', 'domain_uuid');
    }

    public function hotelHousekeepingDefinitions()
    {
        return $this->hasMany(HotelHousekeepingDefinition::class, 'domain_uuid', 'domain_uuid');
    }

    public function hotelPendingActions()
    {
        return $this->hasMany(HotelPendingAction::class, 'domain_uuid', 'domain_uuid');
    }

    public function hotelRoomStatuses()
    {
        return $this->hasMany(HotelRoomStatus::class, 'domain_uuid', 'domain_uuid');
    }

    public function hotelRooms()
    {
        return $this->hasMany(HotelRoom::class, 'domain_uuid', 'domain_uuid');
    }

    public function locations()
    {
        return $this->hasMany(Location::class, 'domain_uuid', 'domain_uuid');
    }

    public function mobileAppUsers()
    {
        return $this->hasMany(MobileAppUsers::class, 'domain_uuid', 'domain_uuid');
    }

    public function provisioningTemplates()
    {
        return $this->hasMany(ProvisioningTemplate::class, 'domain_uuid', 'domain_uuid');
    }

    public function wakeupAuthExts()
    {
        return $this->hasMany(WakeupAuthExt::class, 'domain_uuid', 'domain_uuid');
    }

    public function WakeupCalls()
    {
        return $this->hasMany(WakeupCall::class, 'domain_uuid', 'domain_uuid');
    }

    public function whitelistedNumbers()
    {
        return $this->hasMany(WhitelistedNumbers::class, 'domain_uuid', 'domain_uuid');
    }


    /**
     * Get the settings for the domain.
     */
    public function settings()
    {
        return $this->hasMany(DomainSettings::class, 'domain_uuid', 'domain_uuid');
    }

    /**
     * Get the extensions that belong to the domain.
     */
    public function extensions()
    {
        return $this->hasMany(Extensions::class, 'domain_uuid', 'domain_uuid');
    }

    public function ivrMenus()
    {
        return $this->hasMany(IvrMenus::class, 'domain_uuid', 'domain_uuid');
    }

    public function ivrMenuOptions()
    {
        return $this->hasMany(IvrMenuOptions::class, 'domain_uuid', 'domain_uuid');
    }

    public function devices()
    {
        return $this->hasMany(Devices::class, 'domain_uuid', 'domain_uuid');
    }

    public function deviceLines()
    {
        return $this->hasMany(DeviceLines::class, 'domain_uuid', 'domain_uuid');
    }

    public function deviceLegacyKeys()
    {
        return $this->hasMany(LegacyDeviceKey::class, 'domain_uuid', 'domain_uuid');
    }

    public function deviceLogs()
    {
        return $this->hasMany(DeviceLog::class, 'domain_uuid', 'domain_uuid');
    }

    public function deviceProfiles()
    {
        return $this->hasMany(DeviceProfile::class, 'domain_uuid', 'domain_uuid');
    }

    public function deviceProfileKeys()
    {
        return $this->hasMany(DeviceProfileKey::class, 'domain_uuid', 'domain_uuid');
    }

    public function deviceProfileSettings()
    {
        return $this->hasMany(DeviceProfileSetting::class, 'domain_uuid', 'domain_uuid');
    }

    public function bridges()
    {
        return $this->hasMany(Bridge::class, 'domain_uuid', 'domain_uuid');
    }

    public function callBlocks()
    {
        return $this->hasMany(CallBlock::class, 'domain_uuid', 'domain_uuid');
    }

    public function callCenterQueues()
    {
        return $this->hasMany(CallCenterQueues::class, 'domain_uuid', 'domain_uuid');
    }

    public function callCenterAgents()
    {
        return $this->hasMany(CallCenterAgents::class, 'domain_uuid', 'domain_uuid');
    }

    public function CallCenterQueueAgents()
    {
        return $this->hasMany(CallCenterQueueAgents::class, 'domain_uuid', 'domain_uuid');
    }

    public function callFlows()
    {
        return $this->hasMany(CallFlows::class, 'domain_uuid', 'domain_uuid');
    }

    public function conferenceCenters()
    {
        return $this->hasMany(ConferenceCenters::class, 'domain_uuid', 'domain_uuid');
    }

    public function conferenceRooms()
    {
        return $this->hasMany(ConferenceRoom::class, 'domain_uuid', 'domain_uuid');
    }

    public function conferenceRoomUsers()
    {
        return $this->hasMany(ConferenceRoomUser::class, 'domain_uuid', 'domain_uuid');
    }

    public function conferenceSessions()
    {
        return $this->hasMany(ConferenceSession::class, 'domain_uuid', 'domain_uuid');
    }

    public function conferenceSessionDetails()
    {
        return $this->hasMany(ConferenceSessionDetail::class, 'domain_uuid', 'domain_uuid');
    }

    public function conferenceUsers()
    {
        return $this->hasMany(ConferenceUser::class, 'domain_uuid', 'domain_uuid');
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class, 'domain_uuid', 'domain_uuid');
    }

    public function contactPhones()
    {
        return $this->hasMany(ContactPhones::class, 'domain_uuid', 'domain_uuid');
    }

    public function contactUsers()
    {
        return $this->hasMany(ContactUsers::class, 'domain_uuid', 'domain_uuid');
    }

    public function databaseTransactions()
    {
        return $this->hasMany(DatabaseTransaction::class, 'domain_uuid', 'domain_uuid');
    }

    public function phoneNumbers()
    {
        return $this->hasMany(Destinations::class, 'domain_uuid', 'domain_uuid');
    }

    public function dialplans()
    {
        return $this->hasMany(Dialplans::class, 'domain_uuid', 'domain_uuid');
    }

    public function dialplanDetails()
    {
        return $this->hasMany(DialplanDetails::class, 'domain_uuid', 'domain_uuid');
    }

    public function emailTemplates()
    {
        return $this->hasMany(EmailTemplate::class, 'domain_uuid', 'domain_uuid');
    }
    /**
     * Get the faxes that belong to the domain.
     */
    public function faxes()
    {
        return $this->hasMany(Faxes::class, 'domain_uuid', 'domain_uuid');
    }

    public function faxFiles()
    {
        return $this->hasMany(FaxFiles::class, 'domain_uuid', 'domain_uuid');
    }

    public function faxLogs()
    {
        return $this->hasMany(FaxLogs::class, 'domain_uuid', 'domain_uuid');
    }

    public function faxQueues()
    {
        return $this->hasMany(FaxQueues::class, 'domain_uuid', 'domain_uuid');
    }

    public function followMes()
    {
        return $this->hasMany(FollowMe::class, 'domain_uuid', 'domain_uuid');
    }

    public function followMeDestinations()
    {
        return $this->hasMany(FollowMeDestinations::class, 'domain_uuid', 'domain_uuid');
    }

    public function gateways()
    {
        return $this->hasMany(Gateways::class, 'domain_uuid', 'domain_uuid');
    }

    public function groupPermissions()
    {
        return $this->hasMany(GroupPermissions::class, 'domain_uuid', 'domain_uuid');
    }

    public function groups()
    {
        return $this->hasMany(Groups::class, 'domain_uuid', 'domain_uuid');
    }

    public function musicOnHold()
    {
        return $this->hasMany(MusicOnHold::class, 'domain_uuid', 'domain_uuid');
    }

    public function recordings()
    {
        return $this->hasMany(Recordings::class, 'domain_uuid', 'domain_uuid');
    }

    public function ringGroups()
    {
        return $this->hasMany(RingGroups::class, 'domain_uuid', 'domain_uuid');
    }

    public function ringGroupDestinations()
    {
        return $this->hasMany(RingGroupsDestinations::class, 'domain_uuid', 'domain_uuid');
    }

    public function MessageSettings()
    {
        return $this->hasMany(MessageSetting::class, 'domain_uuid', 'domain_uuid');
    }

    public function Messages()
    {
        return $this->hasMany(Messages::class, 'domain_uuid', 'domain_uuid');
    }

    public function MusicStreams()
    {
        return $this->hasMany(MusicStreams::class, 'domain_uuid', 'domain_uuid');
    }

    public function UserGroups()
    {
        return $this->hasMany(UserGroup::class, 'domain_uuid', 'domain_uuid');
    }

    public function UserLogs()
    {
        return $this->hasMany(UserLog::class, 'domain_uuid', 'domain_uuid');
    }

    /**
     * Get the users that belong to the domain.
     */
    public function users()
    {
        return $this->hasMany(User::class, 'domain_uuid', 'domain_uuid');
    }

    public function UserSettings()
    {
        return $this->hasMany(UserSetting::class, 'domain_uuid', 'domain_uuid');
    }

    /**
     * Get the voicemails that belong to the domain.
     */
    public function voicemails()
    {
        return $this->hasMany(Voicemails::class, 'domain_uuid', 'domain_uuid');
    }

    /**
     * Get the list of users permitted to access the domain.
     */
    public function user_permissions()
    {
        return $this->hasMany(UserDomainPermission::class, 'domain_uuid', 'domain_uuid');
    }

    public function voicemailDestinations()
    {
        return $this->hasMany(VoicemailDestinations::class, 'domain_uuid', 'domain_uuid');
    }

    public function voicemailGreetings()
    {
        return $this->hasMany(VoicemailGreetings::class, 'domain_uuid', 'domain_uuid');
    }

    public function voicemailMessages()
    {
        return $this->hasMany(VoicemailMessages::class, 'domain_uuid', 'domain_uuid');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [];


    /**
     * List of relations that should be fully deleted
     * when a domain is deleted.
     */
public function cascadeRelations(): array
{
    return [
        'activities',
        'archiveRecordings',
        'businessHours',
        'callTranscriptionPolicies',
        'callTranscriptionProviderConfigs',
        'callTranscriptions',

        'deviceCloudProvisionings',
        'domainGroupRelations',

        'emailLogs',

        'emergencyCallMembers',
        'emergencyCalls',

        'hotelHousekeepingDefinitions',
        'hotelPendingActions',
        'hotelRoomStatuses',
        'hotelRooms',

        'locations',
        'mobileAppUsers',
        'provisioningTemplates',

        'user_permissions',

        'wakeupAuthExts',
        'WakeupCalls',

        'whitelistedNumbers',

        'settings',
        'extensions',

        'ivrMenus',
        'ivrMenuOptions',

        'devices',
        'deviceLines',
        'deviceKeys',
        'deviceLogs',
        'deviceProfiles',
        'deviceProfileKeys',
        'deviceProfileSettings',

        'bridges',
        'callBlocks',

        'callCenterQueues',
        'callCenterAgents',
        'CallCenterQueueAgents',

        'callFlows',

        'conferenceCenters',
        'conferenceRooms',
        'conferenceRoomUsers',
        'conferenceSessions',
        'conferenceSessionDetails',
        'conferenceUsers',

        'contacts',
        'contactPhones',
        'contactUsers',

        'databaseTransactions',

        'phoneNumbers',
        'dialplans',
        'dialplanDetails',

        'emailTemplates',

        'faxes',
        'faxFiles',
        'faxLogs',
        'faxQueues',

        'followMes',
        'followMeDestinations',

        'gateways',

        'groupPermissions',
        'groups',

        'musicOnHold',
        'recordings',

        'ringGroups',
        'ringGroupDestinations',

        'MessageSettings',
        'Messages',
        'MusicStreams',
        'UserGroups',
        'UserLogs',
        'users',
        'UserSettings',

        'voicemails',
        'voicemailDestinations',
        'voicemailGreetings',
        'voicemailMessages',
    ];
}

}
