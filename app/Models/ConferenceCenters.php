<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConferenceCenters extends Model
{
    use HasFactory;

    protected $table = "v_conference_centers";

    public $timestamps = false;

    protected $primaryKey = 'conference_center_uuid';

    protected $keyType = 'string';
}
