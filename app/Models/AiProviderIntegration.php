<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProviderIntegration extends Model
{
    protected $table = 'ai_provider_integrations';
    protected $primaryKey = 'provider';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'provider',
        'api_key',
        'public_sip_host',
        'provider_cidrs',
        'enabled',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'provider_cidrs' => 'array',
        'enabled' => 'boolean',
    ];

    protected $hidden = ['api_key'];

    public function hasApiKey(): bool
    {
        return filled($this->api_key);
    }
}
