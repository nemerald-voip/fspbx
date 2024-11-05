<?php

namespace App\Models;

use App\Models\Extensions;
use Illuminate\Database\Eloquent\Model;
use App\Events\ExtensionSuspendedStatusChanged;
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
    protected $hidden = [];


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

        static::saved(function ($model) {
            // Check if the 'status' field was updated
            if ($model->isDirty('suspended')) {
                // Load the relationship
                $model->load('extension');
                event(new ExtensionSuspendedStatusChanged($model));

                $originalSuspended = $model->getOriginal('suspended');
                activity()
                    ->performedOn($model->extension)
                    ->withProperties([
                        'attributes' => ['suspended' => $model->suspended],
                        'old' => ['suspended' => $originalSuspended],
                    ])
                    ->useLog('extension')
                    ->log('updated');
            }
        });
    }

    /**
     * Get the extension this setting belongs to.
     */
    public function extension()
    {
        return $this->hasOne(Extensions::class, 'extension_uuid', 'extension_uuid');
    }
}
