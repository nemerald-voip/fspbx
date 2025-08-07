<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Location extends Model
{
    use HasFactory, TraitUuid;

    protected $table = 'locations';
    protected $primaryKey = 'location_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'location_uuid',
        'domain_uuid',
        'name',
        'description',
    ];

   
}
