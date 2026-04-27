<?php

namespace App\Models;

use App\Services\CallRoutingOptionsService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallFlows extends Model
{
    use HasFactory;

    protected $table = "v_call_flows";

    public $timestamps = false;

    protected $primaryKey = 'call_flow_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'call_flow_uuid',
        'dialplan_uuid',
        'call_flow_name',
        'call_flow_extension',
        'call_flow_feature_code',
        'call_flow_status',
        'call_flow_pin_number',
        'call_flow_label',
        'call_flow_sound',
        'call_flow_app',
        'call_flow_data',
        'call_flow_alternate_label',
        'call_flow_alternate_sound',
        'call_flow_alternate_app',
        'call_flow_alternate_data',
        'call_flow_context',
        'call_flow_enabled',
        'call_flow_group',
        'call_flow_description',
    ];

    protected $appends = [
        'call_flow_status_label',
    ];

    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function getCallFlowStatusLabelAttribute(): ?string
    {
        return $this->call_flow_status !== 'false'
            ? ($this->call_flow_label ?: 'Default Route')
            : ($this->call_flow_alternate_label ?: 'Alternate Route');
    }

    protected function callFlowOptionDetails(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->computeCallFlowOptionDetails(
                $this->call_flow_app,
                $this->call_flow_data
            )
        )->shouldCache();
    }

    protected function callFlowAlternateOptionDetails(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->computeCallFlowOptionDetails(
                $this->call_flow_alternate_app,
                $this->call_flow_alternate_data
            )
        )->shouldCache();
    }

    protected function computeCallFlowOptionDetails(?string $app, ?string $data): array
    {
        if (!filled($app)) {
            return $this->emptyOptionDetails();
        }

        if ($app === 'hangup') {
            return [
                'type' => 'hangup',
                'extension' => null,
                'option' => null,
                'name' => null,
            ];
        }

        $service = new CallRoutingOptionsService($this->domain_uuid);

        return $service->reverseEngineerCallFlowAction(trim("{$app} {$data}"))
            ?? $this->emptyOptionDetails();
    }

    protected function emptyOptionDetails(): array
    {
        return [
            'type' => null,
            'extension' => null,
            'option' => null,
            'name' => null,
        ];
    }

    public function getCallFlowTargetUuidAttribute(): ?string
    {
        return $this->call_flow_option_details['option'] ?? null;
    }

    public function getCallFlowActionAttribute(): ?string
    {
        return $this->call_flow_option_details['type'] ?? null;
    }

    public function getCallFlowActionDisplayAttribute(): ?string
    {
        if (empty($this->call_flow_option_details['type'])) {
            return null;
        }

        return (new CallRoutingOptionsService)
            ->getFriendlyTypeName($this->call_flow_option_details['type']);
    }

    public function getCallFlowTargetNameAttribute(): ?string
    {
        return $this->call_flow_option_details['name'] ?? null;
    }

    public function getCallFlowTargetExtensionAttribute(): ?string
    {
        return $this->call_flow_option_details['extension'] ?? null;
    }

    public function getCallFlowDestinationAttribute(): ?string
    {
        return $this->joinDestination($this->call_flow_app, $this->call_flow_data);
    }

    public function getCallFlowTargetAttribute(): ?array
    {
        return $this->routingTargetForForm(
            $this->call_flow_target_uuid,
            $this->call_flow_target_extension,
            $this->call_flow_target_name
        );
    }

    public function getCallFlowAlternateTargetUuidAttribute(): ?string
    {
        return $this->call_flow_alternate_option_details['option'] ?? null;
    }

    public function getCallFlowAlternateActionAttribute(): ?string
    {
        return $this->call_flow_alternate_option_details['type'] ?? null;
    }

    public function getCallFlowAlternateActionDisplayAttribute(): ?string
    {
        if (empty($this->call_flow_alternate_option_details['type'])) {
            return null;
        }

        return (new CallRoutingOptionsService)
            ->getFriendlyTypeName($this->call_flow_alternate_option_details['type']);
    }

    public function getCallFlowAlternateTargetNameAttribute(): ?string
    {
        return $this->call_flow_alternate_option_details['name'] ?? null;
    }

    public function getCallFlowAlternateTargetExtensionAttribute(): ?string
    {
        return $this->call_flow_alternate_option_details['extension'] ?? null;
    }

    public function getCallFlowAlternateDestinationAttribute(): ?string
    {
        return $this->joinDestination($this->call_flow_alternate_app, $this->call_flow_alternate_data);
    }

    public function getCallFlowAlternateTargetAttribute(): ?array
    {
        return $this->routingTargetForForm(
            $this->call_flow_alternate_target_uuid,
            $this->call_flow_alternate_target_extension,
            $this->call_flow_alternate_target_name
        );
    }

    protected function joinDestination(?string $app, ?string $data): ?string
    {
        if (!filled($app)) {
            return null;
        }

        return $app . ':' . ($data ?? '');
    }

    protected function routingTargetForForm(?string $uuid, ?string $extension, ?string $name): ?array
    {
        if (!filled($extension) && !filled($uuid) && !filled($name)) {
            return null;
        }

        return [
            'value' => $uuid ?: $extension,
            'extension' => $extension,
            'name' => $name ?: $extension,
        ];
    }

    public function generateUniqueSequenceNumber(): ?string
    {
        // Call Flows will have extensions in the range between 9250 and 9299 by default.
        $rangeStart = 9250;
        $rangeEnd = 9299;
        $domainUuid = session('domain_uuid');

        $usedExtensions = Dialplans::where('domain_uuid', $domainUuid)
            ->where('dialplan_number', 'not like', '*%')
            ->pluck('dialplan_number')
            ->merge(
                Voicemails::where('domain_uuid', $domainUuid)
                    ->pluck('voicemail_id')
            )
            ->merge(
                Extensions::where('domain_uuid', $domainUuid)
                    ->pluck('extension')
            )
            ->merge(
                self::where('domain_uuid', $domainUuid)
                    ->pluck('call_flow_extension')
            )
            ->filter(fn ($value) => ctype_digit((string) $value))
            ->map(fn ($value) => (string) (int) $value)
            ->unique()
            ->flip();

        for ($ext = $rangeStart; $ext <= $rangeEnd; $ext++) {
            if (!isset($usedExtensions[(string) $ext])) {
                return (string) $ext;
            }
        }

        return null;
    }
}
