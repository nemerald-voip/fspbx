<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NemeraldAppUsers extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "nemerald_app_users";

    public $timestamps = false;

    protected $primaryKey = 'nemerald_app_user_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

}
