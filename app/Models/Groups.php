<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Groups extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_groups";

    public $timestamps = false;

    protected $primaryKey = 'group_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'group_name',
        'domain_uuid',
        'group_level',
        'group_protected',
        'group_description',
    ];

    /**
     * Get all group permissions
     */
    public function permissions()
    {
        return $this->hasMany(GroupPermissions::class, 'group_uuid', 'group_uuid');
    }


    /**
     * Get all users that belong to the group
     */
    public function user_groups()
    {
        return $this->hasMany(UserGroup::class, 'group_uuid', 'group_uuid');
    }
}
