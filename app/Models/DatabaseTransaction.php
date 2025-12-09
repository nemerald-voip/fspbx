<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DatabaseTransaction extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_database_transactions";

    public $timestamps = false;

    protected $primaryKey = 'database_transaction_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

}
