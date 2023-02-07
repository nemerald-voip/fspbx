<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmailQueue extends Model
{
    use HasFactory, TraitUuid;

    protected $table = "v_email_queue";

    public $timestamps = false;

    protected $primaryKey = 'email_queue_uuid';
    public $incrementing = false;
    protected $keyType = 'string';
}
