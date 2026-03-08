<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;

class ContactAddress extends Model
{
    use TraitUuid;
    
    protected $primaryKey = 'address_uuid';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'contact_addresses';

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