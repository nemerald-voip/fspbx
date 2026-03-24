<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Database\Eloquent\Model;
use libphonenumber\NumberParseException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute; // Added for modern accessors

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
        'media',   
        'delivery_meta',  
        'read_at', 
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'media'   => 'array', 
        'delivery_meta' => 'array', 
        'read_at' => 'datetime', 
    ];

    /**
     * The accessors to append to the model's array form.
     * This ensures your frontend still receives these properties automatically.
     *
     * @var array
     */
    protected $appends = [
        'created_at_formatted',
        'source_formatted',
        'destination_formatted',
    ];

    /**
     * Accessor for created_at_formatted
     */
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

    /**
     * Accessor for source_formatted
     */
    protected function sourceFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->source ? $this->formatPhoneNumber($this->source) : null
        );
    }

    /**
     * Accessor for destination_formatted
     */
    protected function destinationFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->destination ? $this->formatPhoneNumber($this->destination) : null
        );
    }

    /**
     * Get domain that this message belongs to 
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    /**
     * Format Phone Number Helper
     */
    public function formatPhoneNumber($value)
    {
        //Get libphonenumber object
        $phoneNumberUtil = PhoneNumberUtil::getInstance();

        //try to convert phone number to National format
        try {
            $phoneNumberObject = $phoneNumberUtil->parse($value, 'US');
            if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                return $phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
            }
        } catch (NumberParseException $e) {
            // Do nothing, handled by fallback
        }

        // Fallback to original value
        return $value;
    }

    public function updateDeliveryMeta(string $path, mixed $value): void
{
    $meta = $this->delivery_meta ?? [];
    data_set($meta, $path, $value);
    $this->delivery_meta = $meta;
}
}