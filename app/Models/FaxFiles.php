<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class FaxFiles extends Model
{
    use HasFactory, TraitUuid;

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
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function fax()
    {
        return $this->belongsTo(Faxes::class, 'fax_uuid', 'fax_uuid');
    }

    public function faxQueue()
    {
        return $this->belongsTo(FaxQueues::class, 'fax_file_uuid', 'origination_uuid');
    }
}
