<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerNote extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = 'customer_notes';

    protected $primaryKey = 'customer_note_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'note_level',
        'content',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'note_level' => 'integer',
    ];
}
