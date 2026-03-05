<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use \App\Traits\HasContactInfo;

    protected $primaryKey = 'contact_uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'domain_uuid', 'organization_uuid',
        'first_name', 'last_name', 'title', 'department', 'notes'
    ];
    
    public function getFullNameAttribute() {
        return trim("{$this->first_name} {$this->last_name}");
    }

    // Relationships
    public function organization() {
        return $this->belongsTo(Organization::class, 'organization_uuid');
    }

    public function phones() {
        return $this->hasMany(ContactPhone::class, 'contact_uuid');
    }

    public function addresses() {
        return $this->morphMany(ContactAddress::class, 'addressable');
    }

    public function emails() {
        return $this->morphMany(ContactEmail::class, 'emailable');
    }
}