<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dialplans extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;
    
    protected $table = "v_dialplans";

    public $timestamps = false;

    protected $primaryKey = 'dialplan_uuid';
    public $incrementing = false;
    protected $keyType = 'string';


    /**
     * Get the dialplan details this Dialplan object associated with.
     *  returns Eloqeunt Object
     */
    public function dialplan_details()
    {
        return $this->hasMany(DialplanDetails::class,'dialplan_uuid','dialplan_uuid');
    }
}
