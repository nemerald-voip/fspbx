<?php

namespace App\Models;

use App\Models\Extensions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExtensionAdvSettings extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "extension_advanced_settings";

    public $timestamps = false;

    protected $primaryKey = 'setting_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'setting_uuid',
        'extension_uuid',
        'suspended',
        'created_at',
        'updated_at',
    ];


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [

    ];


    protected static function booted()
{
    static::saving(function ($model) {

        // Check if the model is being created or updated
        if ($model->exists) {
            // The model is being updated
            $model->updated_at = now();
        } else {
            // The model is being created
            $model->created_at = now();
            $model->updated_at = now();
        }
    });

}

    /**
     * Get the extension this setting belongs to.
     */
    public function extension()
    {
        return $this->hasOne(Extensions::class,'extension','voicemail_id')
            ->where('domain_uuid', $this->domain_uuid);
    }
 

}
