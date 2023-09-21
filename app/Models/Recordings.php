<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;

class Recordings extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_recordings";

    public $timestamps = false;

    protected $primaryKey = 'recording_uuid';

    protected $keyType = 'string';

    protected $fillable = [
        'recording_filename',
        'recording_name',
        'recording_description',
        'recording_base64'
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct();
        $this->attributes['domain_uuid'] = Session::get('domain_uuid');
        $this->attributes['insert_date'] = date('Y-m-d H:i:s');
        $this->attributes['insert_user'] = Session::get('user_uuid');
        $this->attributes['update_date'] = date('Y-m-d H:i:s');
        $this->attributes['update_user'] = Session::get('user_uuid');
        $this->fill($attributes);
    }
}
