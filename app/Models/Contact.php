<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use \App\Traits\HasContactInfo, TraitUuid;

    protected $primaryKey = 'contact_uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'domain_uuid', 'organization_uuid', 'owner_user_uuid',
        'first_name', 'last_name', 'title', 'department', 'notes'
    ];

    public function scopeVisibleToUser(Builder $query, string $domainUuid, string $userUuid): Builder
    {
        return $query->where('contacts.domain_uuid', $domainUuid)
            ->where(function (Builder $query) use ($userUuid) {
                $query->whereNull('contacts.owner_user_uuid')
                    ->orWhere('contacts.owner_user_uuid', $userUuid);
            });
    }

    public function scopeOwnedByUser(Builder $query, string $domainUuid, string $userUuid): Builder
    {
        return $query->where('contacts.domain_uuid', $domainUuid)
            ->where('contacts.owner_user_uuid', $userUuid);
    }

    public function scopePreferUserOwned(Builder $query, string $userUuid): Builder
    {
        return $query->orderByRaw('CASE WHEN contacts.owner_user_uuid = ? THEN 0 ELSE 1 END', [$userUuid]);
    }
    
    public function getFullNameAttribute() {
        return trim("{$this->first_name} {$this->last_name}");
    }

    // Relationships
    public function organization() {
        return $this->belongsTo(Organization::class, 'organization_uuid');
    }

    public function phones() {
        return $this->morphMany(ContactPhone::class, 'phoneable');
    }

    public function addresses() {
        return $this->morphMany(ContactAddress::class, 'addressable');
    }

    public function emails() {
        return $this->morphMany(ContactEmail::class, 'emailable');
    }
}
