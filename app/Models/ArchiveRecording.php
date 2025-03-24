<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ArchiveRecording extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "archive_recording";
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [

    ];
    public function call_recording()
    {
        return $this->belongsTo(CDR::class,'xml_cdr_uuid','xml_cdr_uuid');
    }
}
