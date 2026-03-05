<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactAddress extends Model
{
    protected $fillable = [
        'domain_uuid', 'label', 
        'street', 'extended', 'city', 'region', 'postal_code', 'country_code',
        'addressable_id', 'addressable_type'
    ];

    public function addressable()
    {
        return $this->morphTo();
    }
}