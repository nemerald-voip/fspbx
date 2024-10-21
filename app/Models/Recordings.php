<?php

namespace App\Models;

use App\Events\GreetingDeleted;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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

    // public function __construct(array $attributes = [])
    // {
    //     parent::__construct();
    //     $this->attributes['domain_uuid'] = Session::get('domain_uuid');
    //     $this->attributes['insert_date'] = date('Y-m-d H:i:s');
    //     $this->attributes['insert_user'] = Session::get('user_uuid');
    //     $this->attributes['update_date'] = date('Y-m-d H:i:s');
    //     $this->attributes['update_user'] = Session::get('user_uuid');
    //     $this->fill($attributes);
    // }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->insert_date = date('Y-m-d H:i:s');
            $model->insert_user = session('user_uuid');
        });

        static::saving(function ($model) {
            if (!$model->domain_uuid) {
                $model->domain_uuid = session('domain_uuid');
            }
            // unset($model->destroy_route);
            // unset($model->messages_route);
        });

        static::retrieved(function ($model) {
            // $model->destroy_route = route('voicemails.destroy', $model);
            // $model->messages_route = route('voicemails.messages.index', $model);
        });

        static::deleted(function ($model) {
            event(new GreetingDeleted($model->recording_uuid, $model->domain_uuid, $model->recording_filename));
        });
    }
}
