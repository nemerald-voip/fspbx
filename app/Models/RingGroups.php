<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RingGroups extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_ring_groups";

    public $timestamps = false;

    protected $primaryKey = 'ring_group_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'domain_uuid',
        'ring_group_extension',
        'ring_group_greeting',
        'ring_group_strategy',
        'ring_group_name',
        'ring_group_call_timeout',
        'ring_group_timeout_app',
        'ring_group_timeout_data',
        'ring_group_cid_name_prefix',
        'ring_group_cid_number_prefix',
        'ring_group_description',
        'ring_group_enabled',
        'ring_group_context',
        'ring_group_forward_enabled',
        'ring_group_forward_destination',
        'ring_group_strategy',
        'ring_group_caller_id_name',
        'ring_group_caller_id_number',
        'ring_group_distinctive_ring',
        'ring_group_ringback',
        'ring_group_call_forward_enabled',
        'ring_group_follow_me_enabled',
        'ring_group_missed_call_app',
        'ring_group_missed_call_data',
        'ring_group_forward_toll_allow',
        'ring_group_forward_context',
        'dialplan_uuid',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user'
    ];


    public function getId()
    {
        return $this->ring_group_extension;
    }

    public function getName()
    {
        return $this->ring_group_extension . ' - ' . $this->ring_group_name;
    }

    public function getNameFormattedAttribute()
    {
        return $this->ring_group_extension . ' - ' . $this->ring_group_name;
    }

    public function getGroupDestinations()
    {
        return $this->belongsTo(RingGroupsDestinations::class, 'ring_group_uuid', 'ring_group_uuid')->orderBy('destination_delay')->get();
    }

    public function destinations()
    {
        return $this->hasMany(RingGroupsDestinations::class, 'ring_group_uuid', 'ring_group_uuid');
    }

    /**
     * Generates a unique sequence number.
     *
     * @return int|null The generated sequence number, or null if unable to generate.
     */
    public function generateUniqueSequenceNumber()
    {

        // Ring groups will have extensions in the range between 9000 and 9099 by default
        $rangeStart = 9000;
        $rangeEnd = 9099;

        $domainUuid = Session::get('domain_uuid');

        // Fetch all used extensions in one combined query
        $usedExtensions = Dialplans::where('domain_uuid', $domainUuid)
            ->where('dialplan_number', 'not like', '*%')
            ->pluck('dialplan_number')
            ->unique();

        // Find the first available extension
        for ($ext = $rangeStart; $ext <= $rangeEnd; $ext++) {
            if (!$usedExtensions->contains($ext)) {
                // This is your unique extension
                $uniqueExtension = $ext;
                break;
            }
        }

        if (isset($uniqueExtension)) {
            return $uniqueExtension;
        }

        // Return null if unable to generate a unique sequence number
        return null;
    }
}
