<?php

namespace App\Models;

use libphonenumber\PhoneNumberFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Destinations extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_destinations";

    public $timestamps = false;

    protected $primaryKey = 'destination_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'destination_uuid',
        'domain_uuid',
        'dialplan_uuid',
        'fax_uuid',
        'user_uuid',
        'destination_type',
        'destination_number',
        'destination_trunk_prefix',
        'destination_area_code',
        'destination_prefix',
        'destination_condition_field',
        'destination_number_regex',
        'destination_caller_id_name',
        'destination_caller_id_number',
        'destination_cid_name_prefix',
        'destination_actions',
        'destination_conditions',
        'destination_context',
        'destination_record',
        'destination_hold_music',
        'destination_accountcode',
        'destination_type_voice',
        'destination_type_fax',
        'destination_type_emergency',
        'destination_type_text',
        'destination_app',
        'destination_data',
        'destination_distinctive_ring',
        'destination_alternate_app',
        'destination_alternate_data',
        'destination_order',
        'destination_enabled',
        'destination_description',
        'group_uuid',
    ];

    protected $appends = ['destination_number_formatted', 'routing_options'];

    public static function boot()
    {
        parent::boot();
        static::saving(function ($model) {
            // Always update these fields before save
            $destination_numbers = array_filter([
                'destination_prefix' => $model->destination_prefix,
                'destination_trunk_prefix' => $model->destination_trunk_prefix,
                'destination_area_code' => $model->destination_area_code,
                'destination_number' => $model->destination_number,
            ]);
            $model->attributes['destination_number_regex'] = $model->to_regex($destination_numbers);

            $model->attributes['update_date'] = date('Y-m-d H:i:s');
            $model->attributes['update_user'] = session('user_uuid');
        });
    }

    public function getDestinationNumberFormattedAttribute()
    {
        return formatPhoneNumber($this->destination_number, 'US', PhoneNumberFormat::NATIONAL);
    }

    public function getDestinationNumberE164Attribute()
    {
        return formatPhoneNumber($this->destination_number, 'US', PhoneNumberFormat::E164);
    }

    public function getRoutingOptionsAttribute()
    {
        if (empty($this->destination_actions)) {
            return null;
        }
        $service = new \App\Services\CallRoutingOptionsService($this->domain_uuid);
        return $service->reverseEngineerDestinationActions($this->destination_actions);
    }

    public function getLabelAttribute()
    {
        $phoneNumberFormatted = $this->destination_number_formatted;
        if (!empty($this->destination_description)) {
            return $phoneNumberFormatted . ' - ' . $this->destination_description;
        }
        return $phoneNumberFormatted;
    }


    /**
     * Get domain that this model belongs to
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function fax()
    {
        return $this->belongsTo(Faxes::class, 'fax_uuid', 'fax_uuid');
    }


    /**
     * Convert destination number to a regular expression
     *
     * @param array $array
     * @return string
     */
    public function to_regex($array)
    {
        $regex_parts = [];

        // If all elements are present
        if (!empty($array['destination_prefix']) && !empty($array['destination_trunk_prefix']) && !empty($array['destination_area_code']) && !empty($array['destination_number'])) {
            $regex_parts[] = "\+?{$array['destination_prefix']}?({$array['destination_area_code']}{$array['destination_number']})";
            $regex_parts[] = "{$array['destination_trunk_prefix']}?({$array['destination_area_code']}{$array['destination_number']})";
            $regex_parts[] = "({$array['destination_area_code']}{$array['destination_number']})";
        }
        // Handle cases with missing elements
        elseif (!empty($array['destination_prefix']) && !empty($array['destination_trunk_prefix']) && !empty($array['destination_number'])) {
            $regex_parts[] = "\+?{$array['destination_prefix']}?({$array['destination_number']})";
            $regex_parts[] = "{$array['destination_trunk_prefix']}?({$array['destination_number']})";
        } elseif (!empty($array['destination_prefix']) && !empty($array['destination_area_code']) && !empty($array['destination_number'])) {
            $regex_parts[] = "\+?{$array['destination_prefix']}?({$array['destination_area_code']}{$array['destination_number']})";
        } elseif (!empty($array['destination_number'])) {
            $destination_prefix = $array['destination_prefix'] ?? '';
            $destination_number = $array['destination_number'];

            // Add capturing group for the destination number
            $destination_regex = "($destination_number)";

            // Escape "+" in the number if present
            if (strpos($destination_number, '+') === 0) {
                $destination_regex = "\\+?" . substr($destination_number, 1);
            }

            // Add prefix handling
            if (!empty($destination_prefix)) {
                $destination_prefix = str_replace("+", "", $destination_prefix);
                $plus = '\+?';
                if (strlen($destination_prefix) == 1) {
                    $destination_prefix = $plus . $destination_prefix . '?';
                } else {
                    $destination_prefix = $plus . '(?:' . $destination_prefix . ')?';
                }
            }

            // Convert N, X, Z patterns to regex
            $destination_regex = str_ireplace(["N", "X", "Z"], ["[2-9]", "[0-9]", "[1-9]"], $destination_regex);

            // Ensure regex starts with "^" and ends with "$"
            $destination_regex = "^" . $destination_prefix . $destination_regex . "$";

            return $destination_regex;
        }

        // Combine regex parts into one pattern with capturing group
        if (!empty($regex_parts)) {
            return "^(" . implode('|', $regex_parts) . ")$";
        }

        return ''; // Return empty string if no valid regex is generated
    }
}
