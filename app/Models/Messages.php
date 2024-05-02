<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Messages extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "messages";

    protected $primaryKey = 'message_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'message_uuid',
        'extension_uuid',
        'domain_uuid',
        'source',
        'destination',
        'message',
        'direction',
        'type',
        'reference_id',
        'status',
        'created_at',
        'updated_at',
    ];

}