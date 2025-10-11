<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * EmailLog
 *
 * @property string $from
 * @property string $to
 * @property string $cc
 * @property string $bcc
 * @property string $subject
 * @property string $text_body
 * @property string $html_body
 * @property string $raw_body
 * @property string $sent_debug_info
 * @property Carbon|null $created_at
 */
class EmailLog extends Model
{
    use HasFactory;
    use Prunable;
    use \App\Models\Traits\TraitUuid;

    protected $table = 'email_log';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'attachments' => 'json',
    ];

    protected $appends = [
        'created_at_formatted',
    ];

    public function getCreatedAtFormattedAttribute()
    {
        if (!$this->created_at) {
            return null;
        }
        $timeZone = auth()->user()->time_zone;
        return Carbon::parse($this->created_at)->setTimezone($timeZone)->format('g:i:s A M d, Y');
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }



    public function prunable()
    {
        return static::where('created_at', '<=', now()->subDays(config('email-log.keep_email_for_days', 90)));
    }


}