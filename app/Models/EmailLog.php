<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * EmailLog
 *
 * @property string $from
 * @property string $to
 * @property string $cc
 * @property string $bcc
 * @property string $subject
 * @property string $text_body
 * @property string $html_body
 * @property string $raw_body
 * @property string $sent_debug_info
 * @property Carbon|null $created_at
 */
class EmailLog extends Model
{
    use HasFactory;
    use Prunable;
    use \App\Models\Traits\TraitUuid;

    protected $table = 'email_log';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'attachments' => 'json',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    // protected static function booted(): void
    // {
    //     static::addGlobalScope('domain', function (Builder $query) {
    //         if (! app()->runningInConsole()) {
    //             if (auth()->check() && session('domain_uuid')) {
    //                 $query->where('domain_uuid', session('domain_uuid'));
    //             } else {
    //                 $query->where('domain_uuid',null);
    //             }
    //         }
    //     });
    // }

    public static function boot()
    {
        parent::boot();

        // self::deleting(function ($record) {
        //     $folderPath = '';
        //     $storageDisk = config('filament-email.attachments_disk', 'local');
        //     if (! empty($record->attachments)) {
        //         foreach ($record->attachments as $attachment) {
        //             $filePath = Storage::disk($storageDisk)->path($attachment['path']);
        //             if (empty($folderPath)) {
        //                 $parts = explode(DIRECTORY_SEPARATOR, $attachment['path']);
        //                 array_pop($parts);
        //                 $folderPath = implode(DIRECTORY_SEPARATOR, $parts);
        //             }
        //             if (Storage::disk($storageDisk)->exists($attachment['path'])) {
        //                 Storage::disk($storageDisk)->delete($attachment['path']);
        //             }
        //         }
        //     }
        //     if (! empty($record->raw_body) && count(explode(DIRECTORY_SEPARATOR, $record->raw_body)) === 3) {
        //         if (Storage::disk($storageDisk)->exists($record->raw_body)) {
        //             if (empty($folderPath)) {
        //                 $parts = explode(DIRECTORY_SEPARATOR, $record->raw_body);
        //                 array_pop($parts);
        //                 $folderPath = implode(DIRECTORY_SEPARATOR, $parts);
        //             }
        //             Storage::disk($storageDisk)->delete($record->raw_body);
        //         }
        //     }
        //     if (! empty($folderPath) && Storage::disk($storageDisk)->directoryExists($folderPath)) {
        //         Storage::disk($storageDisk)->deleteDirectory($folderPath);
        //     }
        // });
    }

    public function prunable()
    {
        return static::where('created_at', '<=', now()->subDays(config('email-log.keep_email_for_days', 90)));
    }


}