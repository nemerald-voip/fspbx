<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhonebookContact extends Model
{
    use TraitUuid;

    protected $table = 'phonebook_contacts';
    protected $primaryKey = 'phonebook_contact_uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'phonebook_contact_uuid',
        'phonebook_uuid',
        'domain_uuid',
        'first_name',
        'last_name',
        'phone_number',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function phonebook(): BelongsTo
    {
        return $this->belongsTo(Phonebook::class, 'phonebook_uuid');
    }
}
