<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CDR extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_xml_cdr";

    public $timestamps = false;

    protected $primaryKey = 'xml_cdr_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'record_name',
    ];



    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [

    ];
    public function archive_recording()
    {
        return $this->hasOne(ArchiveRecording::class,'xml_cdr_uuid','xml_cdr_uuid');
    }
}

