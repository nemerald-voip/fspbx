<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserGroup extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_user_groups";

    public $timestamps = false;

    protected $primaryKey = 'user_group_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_group_uuid',
        'domain_uuid',
        'group_name',
        'group_uuid',
        'user_uuid',
    ];

    public function user()
    {
        return $this->belongsTo(User::class,'user_uuid','user_uuid');
    }

    public function group()
    {
        return $this->belongsTo(Groups::class,'group_uuid','group_uuid');
    }

}
