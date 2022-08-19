<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MobileAppUsers extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "mobile_app_users";

    public $timestamps = true;

    protected $primaryKey = 'mobile_app_user_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

}
