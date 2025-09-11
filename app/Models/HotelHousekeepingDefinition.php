<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelHousekeepingDefinition extends Model
{
    protected $table = 'hotel_housekeeping_definitions';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['uuid','domain_uuid','code','label','enabled'];
    protected $casts = ['enabled'=>'boolean','code'=>'integer'];

    // Scopes for clean querying
    public function scopeEnabled($q)          { return $q->where('enabled', true); }
    public function scopeGlobalOnly($q)       { return $q->whereNull('domain_uuid'); }
    public function scopeForDomain($q, $uuid) { return $q->where('domain_uuid', $uuid); }
}
