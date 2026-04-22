<?php

namespace App\Models;

use Carbon\Carbon;
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

    protected $appends = [
        'fax_date_formatted',
    ];

    protected $casts = [
        'fax_epoch' => 'integer',
    ];


    public function faxFile()
    {
        return $this->hasOne(FaxFiles::class, 'fax_file_uuid', 'fax_log_uuid');
    }

    public function getFaxDateFormattedAttribute()
    {
        if (!$this->fax_epoch || !$this->domain_uuid) {
            return null;
        }

        $timeZone = get_local_time_zone($this->domain_uuid);

        return Carbon::createFromTimestamp($this->fax_epoch, 'UTC')
            ->setTimezone($timeZone)
            ->format('g:i:s A M d, Y');
    }

    public function fax()
    {
        return $this->belongsTo(Faxes::class, 'fax_uuid', 'fax_uuid');
    }
}
