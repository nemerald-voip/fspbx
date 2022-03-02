<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Domain extends Model
{
    use HasApiTokens, HasFactory, Notifiable, \App\Models\Traits\TraitUuid;

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
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [

    ];

}
