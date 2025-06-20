<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmergencyCallEmail extends Model
{
    use HasApiTokens, HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "emergency_call_emails";

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
        'emergency_call_uuid',
        'email',
    ];

    public function emergencyCall()
    {
        return $this->belongsTo(EmergencyCall::class);
    }

}
