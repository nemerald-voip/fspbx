<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiAgentKbDocument extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = 'v_ai_agent_kb_documents';

    public $timestamps = false;

    protected $primaryKey = 'kb_document_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'kb_document_uuid',
        'ai_agent_uuid',
        'domain_uuid',
        'document_type',
        'elevenlabs_documentation_id',
        'name',
        'file_path',
        'file_mime_type',
        'file_size',
        'url',
        'text_content',
        'sync_status',
        'sync_error',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user',
    ];

    public function aiAgent()
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_uuid', 'ai_agent_uuid');
    }
}
