<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ArchiveRecording extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "archive_recording";
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    // protected $fillable = [
    //     'domain_name',
    //     'domain_enabled',
    //     'domain_description'
    // ];

    /**
     * Get the settings for the domain.
     */


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
