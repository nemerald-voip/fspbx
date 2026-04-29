<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessControlNode extends Model
{
    use TraitUuid;

    protected $table = 'v_access_control_nodes';
    protected $primaryKey = 'access_control_node_uuid';

    public const CREATED_AT = 'insert_date';
    public const UPDATED_AT = 'update_date';

    protected $fillable = [
        'access_control_node_uuid',
        'access_control_uuid',
        'node_type',
        'node_cidr',
        'node_description',
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

    public function accessControl(): BelongsTo
    {
        return $this->belongsTo(AccessControl::class, 'access_control_uuid', 'access_control_uuid');
    }
}
