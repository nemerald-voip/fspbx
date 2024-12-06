<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SwitchVariable extends Model
{
    use HasFactory;

    protected $table = 'v_vars';

    public $timestamps = false;

    protected $primaryKey = 'var_uuid';

    protected $keyType = 'string';

    // Add guarded or fillable fields based on your preference
    protected $guarded = [];

}

