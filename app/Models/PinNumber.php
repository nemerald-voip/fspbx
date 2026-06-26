<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PinNumber extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = 'v_pin_numbers';

    public $timestamps = false;

    protected $primaryKey = 'pin_number_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }
}
