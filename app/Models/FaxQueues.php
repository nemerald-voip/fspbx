<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaxQueues extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;
    
    protected $table = "v_fax_queue";

    public $timestamps = false;

    protected $primaryKey = 'fax_queue_uuid';
    public $incrementing = false;
    protected $keyType = 'string';


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'fax_queue_uuid',
        'domain_uuid',
        'fax_uuid',
        'origination_uuid',
        'fax_date',
        'hostname',
        'fax_caller_id_name',
        'fax_caller_id_number',
        'fax_number',
        'fax_prefix',
        'fax_email_address',
        'fax_file',
        'fax_status',
        'fax_retry_date',
        'fax_notify_sent',
        'fax_notify_date',
        'fax_retry_count',
        'fax_accountcode',
        'fax_command',
        'fax_log_uuid',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user'
    ];
}
