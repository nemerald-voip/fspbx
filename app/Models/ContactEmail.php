<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactEmail extends Model
{
    protected $fillable = ['email_address', 'label', 'emailable_id', 'emailable_type'];

    public function emailable()
    {
        return $this->morphTo();
    }
}