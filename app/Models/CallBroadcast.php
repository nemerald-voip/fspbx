<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallBroadcast extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_call_broadcasts";

    public $timestamps = false;

    protected $primaryKey = 'call_broadcast_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

}
