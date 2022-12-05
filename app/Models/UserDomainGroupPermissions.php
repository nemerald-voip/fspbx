<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDomainGroupPermissions extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "user_domain_group_permissions";

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'domain_group_uuid',
        'user_uuid'
    ];

    public function domain_group()
    {
        return $this->belongsTo(DomainGroups::class,'domain_group_uuid','domain_group_uuid');
    }

    // public function domain()
    // {
    //     return $this->belongsTo(Domain::class,'domain_uuid','domain_uuid');
    // }

}
