<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaxAllowedDomainNames extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;
    
    protected $table = "fax_allowed_domain_names";

    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';


    /**
     * Get the fax for this domain name
     */
    public function fax()
    {
        return $this->belongsTo(Faxes::class, 'fax_uuid', 'fax_uuid');
    }
}
