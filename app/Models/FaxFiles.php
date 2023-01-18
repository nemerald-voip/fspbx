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


class FaxFiles extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_fax_files";

    public $timestamps = false;

    protected $primaryKey = 'fax_file_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    
    /**
     * Get the domain to which this faxfile belongs 
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class,'domain_uuid','domain_uuid');
    }
    public function fax()
    {
        return $this->belongsTo(Faxes::class,'fax_uuid','fax_uuid');
    }
}
