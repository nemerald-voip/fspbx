<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Domain extends Model
{
    use HasApiTokens, HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_domains";

    public $timestamps = false;

    protected $primaryKey = 'domain_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'domain_name',
        'domain_enabled',
        'domain_description'
    ];

    /**
     * Get the settings for the domain.
     */
    public function settings()
    {
        return $this->hasMany(DomainSettings::class,'domain_uuid','domain_uuid');
    }

    /**
     * Get the users that belong to the domain.
     */
    public function users()
    {
        return $this->hasMany(User::class,'domain_uuid','domain_uuid');
    }

    /**
     * Get the voicemails that belong to the domain.
     */
    public function voicemails()
    {
        return $this->hasMany(Voicemails::class,'domain_uuid','domain_uuid');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [

    ];

}
