<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class PcapSession extends Model
{
    use HasFactory, TraitUuid;

    public const DIRECTION_INBOUND = 'inbound';
    public const DIRECTION_OUTBOUND = 'outbound';
    public const DIRECTION_LOCAL = 'local';
    public const DIRECTIONS = [self::DIRECTION_INBOUND, self::DIRECTION_OUTBOUND, self::DIRECTION_LOCAL];

    public const SCOPE_ALL = 'all';
    public const SCOPE_EXTENSIONS = 'extensions';

    // Duration key => minutes.
    public const DURATIONS = [
        '1h' => 60,
        '4h' => 240,
        '8h' => 480,
        '24h' => 1440,
        '48h' => 2880,
        '1w' => 10080,
    ];

    // Hours files are kept after capturing ends. 72 is the hard maximum.
    public const KEEP_HOURS = [24, 48, 72];

    protected $table = 'pcap_sessions';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'name',
        'duration',
        'directions',
        'scope',
        'extension_uuids',
        'started_at',
        'expires_at',
        'stopped_at',
        'keep_hours',
        'delete_at',
        'created_by',
    ];

    protected $casts = [
        'directions' => 'array',
        'extension_uuids' => 'array',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'stopped_at' => 'datetime',
        'keep_hours' => 'integer',
        'delete_at' => 'datetime',
    ];

    public function captures(): HasMany
    {
        return $this->hasMany(PcapCapture::class, 'pcap_session_uuid', 'uuid');
    }

    /**
     * Sessions that should be capturing new calls right now.
     */
    public function scopeCapturing(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= now();

        return $query->whereNull('stopped_at')
            ->where('started_at', '<=', $at)
            ->where('expires_at', '>', $at);
    }

    public function isCapturing(): bool
    {
        return $this->stopped_at === null && $this->expires_at->isFuture();
    }

    /**
     * Whether a call that started at $epoch belongs to this session's capture window.
     */
    public function coversEpoch(int $epoch): bool
    {
        if ($epoch < $this->started_at->getTimestamp()) {
            return false;
        }

        return $epoch <= $this->captureEndsAt()->getTimestamp();
    }

    public function captureEndsAt(): Carbon
    {
        return $this->stopped_at !== null && $this->stopped_at->lt($this->expires_at) ? $this->stopped_at : $this->expires_at;
    }

    public function storageDirectory(): string
    {
        return "pcaps/{$this->domain_uuid}/{$this->uuid}";
    }
}
