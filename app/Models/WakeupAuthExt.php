<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WakeupAuthExt extends Model
{
    // Define the table name
    protected $table = 'wakeup_auth_ext';
    
    // Specify the primary key and its type
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    // Specify which fields can be mass assigned
    protected $fillable = [
        'domain_uuid',
        'extension_uuid',
    ];

}
