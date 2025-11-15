<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CallTranscriptionProvider extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;
    
    protected $table = 'call_transcription_providers';

    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'name', 'is_active'];

}
