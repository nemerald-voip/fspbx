<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BusinessHourPeriod extends Model
{
    use HasFactory, TraitUuid;

    protected $table = 'business_hour_periods';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'business_hour_uuid',
        'day_of_week',
        'start_time',
        'end_time',
        'action',
        'target_type',
        'target_id',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'start_time'  => 'string',
        'end_time'    => 'string',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    /**
     * The parent business hour definition.
     */
    public function businessHour(): BelongsTo
    {
        return $this->belongsTo(BusinessHour::class, 'business_hour_uuid', 'uuid');
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
