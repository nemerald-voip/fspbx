<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallBlock extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_call_block";

    public $timestamps = false;

    protected $primaryKey = 'call_block_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

}
