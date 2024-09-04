<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoicemailGreetings extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_voicemail_greetings";

    public $timestamps = false;

    protected $primaryKey = 'voicemail_greeting_uuid';
    public $incrementing = false;
    protected $keyType = 'string';


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'domain_uuid',
        'voicemail_id',
        'greeting_id',
        'greeting_name',
        'greeting_filename',
        'greeting_description',
        'greeting_base64',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user'
    ];
}
