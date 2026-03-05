<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use \App\Traits\HasContactInfo; 

    protected $primaryKey = 'organization_uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['domain_uuid', 'name', 'website', 'notes'];

    // Relationships
    public function contacts() {
        return $this->hasMany(Contact::class, 'organization_uuid');
    }
    
    public function addresses() {
        return $this->morphMany(ContactAddress::class, 'addressable');
    }

    public function emails() {
        return $this->morphMany(ContactEmail::class, 'emailable');
    }

}
