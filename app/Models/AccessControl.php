<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccessControl extends Model
{
    use TraitUuid;

    protected $table = 'v_access_controls';
    protected $primaryKey = 'access_control_uuid';

    public const CREATED_AT = 'insert_date';
    public const UPDATED_AT = 'update_date';

    protected $fillable = [
        'access_control_uuid',
        'access_control_name',
        'access_control_default',
        'access_control_description',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->insert_user = $model->insert_user ?: session('user_uuid');
            $model->update_user = $model->update_user ?: session('user_uuid');
        });

        static::updating(function (self $model) {
            $model->update_user = session('user_uuid');
        });
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(AccessControlNode::class, 'access_control_uuid', 'access_control_uuid')
            ->orderBy('node_cidr');
    }
}
