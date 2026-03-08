<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;

class ContactEmail extends Model
{
    use TraitUuid;
    
    // 1. Define the custom Primary Key
    protected $primaryKey = 'email_uuid';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'contact_emails';

    protected $fillable = ['email_address', 'label', 'emailable_id', 'emailable_type'];

    public function emailable()
    {
        return $this->morphTo();
    }
}