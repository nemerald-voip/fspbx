<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExtensionUser extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_extension_users";

    public $timestamps = false;

    protected $primaryKey = 'extension_user_uuid';
    public $incrementing = false;
    protected $keyType = 'string';



}
