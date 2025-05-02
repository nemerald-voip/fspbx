<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessHourException extends Model
{
    use HasFactory, TraitUuid;

    protected $table = 'business_hour_exceptions';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'business_hour_uuid',
        'exception_date',
        'start_time',
        'end_time',
        'note',
    ];

    protected $casts = [
        'exception_date' => 'date',
        'start_time'     => 'string',
        'end_time'       => 'string',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    /**
     * The parent business hour definition.
     */
    public function businessHour(): BelongsTo
    {
        return $this->belongsTo(BusinessHour::class, 'business_hour_uuid', 'uuid');
    }
}
