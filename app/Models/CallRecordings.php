<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CallRecordings extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_call_recordings";

    public $timestamps = false;

    protected $primaryKey = 'call_recording_uuid';
    public $incrementing = false;
    protected $keyType = 'string';


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [

    ];
    public function archive_recording()
    {
        return $this->hasOne(ArchiveRecording::class,'call_recording_uuid','call_recording_uuid');
    }
}

