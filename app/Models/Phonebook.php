<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Phonebook extends Model
{
    use TraitUuid;

    protected $table = 'phonebooks';
    protected $primaryKey = 'phonebook_uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'phonebook_uuid',
        'domain_uuid',
        'name',
        'description',
        'enabled',
        'is_default',
        'include_extensions',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'is_default' => 'boolean',
        'include_extensions' => 'boolean',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(PhonebookContact::class, 'phonebook_uuid')
            ->orderBy('sort_order')
            ->orderBy('first_name');
    }

    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(
            Devices::class,
            'device_phonebook',
            'phonebook_uuid',
            'device_uuid'
        )->withPivot('slot')->withTimestamps();
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    protected static function booted()
    {
        static::deleting(function (self $phonebook) {
            $phonebook->contacts()->delete();
            $phonebook->devices()->detach();
        });
    }
}
