<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use App\Models\EmergencyCallMember;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmergencyCall extends Model
{
    use HasApiTokens, HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "emergency_calls";

    public $timestamps = false;

    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'domain_uuid',
        'emergency_number',
        'prompt',
        'description',
    ];


    public function members()
    {
        return $this->hasMany(EmergencyCallMember::class);
    }
}
