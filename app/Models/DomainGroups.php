<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DomainGroups extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "domain_groups";

    protected $primaryKey = 'domain_group_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'group_name',
    ];


    /**
     * Get all domain groups relations
     */
    public function domain_group_relations()
    {
        return $this->hasMany(DomainGroupRelations::class,'domain_group_uuid','domain_group_uuid');
    }


}
