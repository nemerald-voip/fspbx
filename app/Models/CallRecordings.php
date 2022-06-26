<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
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
}
