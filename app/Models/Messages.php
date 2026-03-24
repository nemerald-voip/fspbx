<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Database\Eloquent\Model;
use libphonenumber\NumberParseException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Messages extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "messages";

    protected $primaryKey = 'message_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

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
        'media',
        'delivery_meta',
        'read_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'media'         => 'array',
        'delivery_meta' => 'array',
        'read_at'       => 'datetime',
    ];

    protected $appends = [
        'created_at_formatted',
        'source_formatted',
        'destination_formatted',
    ];

    protected function createdAtFormatted(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->created_at || !$this->domain_uuid) {
                    return null;
                }

                $time_zone = get_local_time_zone($this->domain_uuid);

                return Carbon::parse($this->created_at)
                    ->setTimezone($time_zone)
                    ->format('g:i:s A M d, Y');
            }
        );
    }

    protected function sourceFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->source ? $this->formatPhoneNumber($this->source) : null
        );
    }

    protected function destinationFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->destination ? $this->formatPhoneNumber($this->destination) : null
        );
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function updateDeliveryMeta(string $path, mixed $value): void
    {
        $meta = $this->delivery_meta ?? [];
        data_set($meta, $path, $value);
        $this->delivery_meta = $meta;
    }

    public function formatPhoneNumber($value)
    {
        $phoneNumberUtil = PhoneNumberUtil::getInstance();

        try {
            $phoneNumberObject = $phoneNumberUtil->parse($value, 'US');

            if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                return $phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
            }
        } catch (NumberParseException $e) {
        }

        return $value;
    }
}