<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CeretaxTransaction extends Model
{
    protected $table = 'ceretax_transactions';

    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'uuid',
        'invoice_number',
        'status',
        'ksuid',
        'stan',
        'request_json',
        'response_json',
        'http_status',
        'error_summary',
        'env',
    ];

    protected $casts = [
        'request_json'  => 'array',
        'response_json' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (empty($m->uuid)) {
                $m->uuid = (string) Str::uuid();
            }
        });
    }
}
