<?php

namespace App\Models;

use App\Services\CallRoutingOptionsService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Session;

class AiReceptionist extends Model
{
    use HasFactory, Traits\GeneratesUniqueExtensions, Traits\TraitUuid;

    protected $table = 'ai_receptionists';
    protected $primaryKey = 'ai_receptionist_uuid';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    protected $casts = [
        'allow_interruptions' => 'boolean',
        'min_interruption_duration' => 'float',
        'transcript_enabled' => 'boolean',
        'tool_access_enabled' => 'boolean',
    ];

    protected function fallbackOptionDetails(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->computeFallbackOptionDetails()
        )->shouldCache();
    }

    public function __construct(array $attributes = [])
    {
        parent::__construct();
        $this->attributes['domain_uuid'] = Session::get('domain_uuid');
        $this->fill($attributes);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AiReceptionistSession::class, 'ai_receptionist_uuid', 'ai_receptionist_uuid');
    }

    public function tools(): HasMany
    {
        return $this->hasMany(AiReceptionistTool::class, 'ai_receptionist_uuid', 'ai_receptionist_uuid');
    }

    public function generateUniqueSequenceNumber(): ?string
    {
        return $this->firstAvailableExtensionInRange(9450, 9499);
    }

    public function getFallbackTargetUuidAttribute(): ?string
    {
        return $this->fallback_option_details['option'] ?? null;
    }

    public function getFallbackTargetNameAttribute(): ?string
    {
        return $this->fallback_option_details['name'] ?? null;
    }

    public function getFallbackTargetExtensionAttribute(): ?string
    {
        return $this->fallback_option_details['extension'] ?? null;
    }

    private function computeFallbackOptionDetails(): array
    {
        if (blank($this->fallback_type)) {
            return [
                'type' => null,
                'extension' => null,
                'option' => null,
                'name' => null,
            ];
        }

        if ($this->fallback_type === 'bridges') {
            $action = 'bridge ' . $this->fallback_target;
        } elseif ($this->fallback_type === 'voicemails') {
            $action = 'transfer *99' . $this->fallback_target . ' XML ' . optional($this->domain)->domain_name;
        } elseif (in_array($this->fallback_type, ['check_voicemail', 'company_directory', 'hangup'], true)) {
            $action = match ($this->fallback_type) {
                'check_voicemail' => 'transfer *98 XML ' . optional($this->domain)->domain_name,
                'company_directory' => 'transfer *411 XML ' . optional($this->domain)->domain_name,
                default => 'hangup',
            };
        } else {
            $action = 'transfer ' . $this->fallback_target . ' XML ' . optional($this->domain)->domain_name;
        }

        return (new CallRoutingOptionsService($this->domain_uuid))
            ->reverseEngineerRingGroupExitAction($action) ?? [
                'type' => $this->fallback_type,
                'extension' => $this->fallback_target,
                'option' => null,
                'name' => $this->fallback_label,
            ];
    }
}
