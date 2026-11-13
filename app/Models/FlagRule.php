<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlagRule extends Model
{
    use HasFactory, TraitUuid;

    public const TYPE_WEEKLY = 'weekly';
    public const TYPE_DATE_RANGE = 'date_range';

    protected $table = 'flag_rules';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'flag_uuid',
        'rule_type',
        'weekdays',
        'start_time',
        'end_time',
        'start_date',
        'end_date',
    ];

    public function flag(): BelongsTo
    {
        return $this->belongsTo(Flag::class, 'flag_uuid', 'uuid');
    }
}
