<?php

namespace App\Models;

use text;
use database;
use Exception;
use Throwable;
use permisssions;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Jobs\SendFaxNotificationToSlack;
use libphonenumber\NumberParseException;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Symfony\Component\Process\Exception\ProcessFailedException;


class FaxLogs extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_fax_logs";

    public $timestamps = false;

    protected $primaryKey = 'fax_log_uuid';
    public $incrementing = false;
    protected $keyType = 'string';


}
