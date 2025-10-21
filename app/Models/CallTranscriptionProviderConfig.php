<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CallTranscriptionProviderConfig extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = 'call_transcription_provider_config';

    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['tenant_uuid', 'provider_uuid', 'config'];

    // Store/retrieve JSONB as array; AsArrayObject keeps array semantics
    protected $casts = ['config' => AsArrayObject::class];

    public function provider() 
    { 
        return $this->belongsTo(CallTranscriptionProvider::class, 'provider_uuid', 'uuid'); 
    }
}
