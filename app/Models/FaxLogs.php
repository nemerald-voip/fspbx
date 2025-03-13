<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class FaxLogs extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_fax_logs";

    public $timestamps = false;

    protected $primaryKey = 'fax_log_uuid';
    public $incrementing = false;
    protected $keyType = 'string';


}
