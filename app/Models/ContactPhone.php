<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;

class ContactPhone extends Model
{
    use TraitUuid;
    
    protected $primaryKey = 'phone_uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['phone_number', 'label', 'phoneable_id', 'phoneable_type'];

    // Get the owner (Contact or Organization)
    public function phoneable()
    {
        return $this->morphTo();
    }
}