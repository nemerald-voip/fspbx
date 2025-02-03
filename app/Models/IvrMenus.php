<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\CallRoutingOptionsService;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IvrMenus extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_ivr_menus";

    public $timestamps = false;

    protected $primaryKey = 'ivr_menu_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /*
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     *
     */
    protected $fillable = [
        'domain_uuid',
        'ivr_menu_name',
        'ivr_menu_extension',
        'ivr_menu_description',
        'ivr_menu_greet_long',
        'ivr_menu_enabled',
        'ivr_menu_digit_len',
        'ivr_menu_timeout',
        'ivr_menu_ringback',
        'ivr_menu_invalid_sound',
        'ivr_menu_exit_sound',
        'ivr_menu_direct_dial',
        'ivr_menu_max_failures',
        'ivr_menu_max_timeouts',
        'ivr_menu_exit_app',
        'ivr_menu_exit_data',
        'ivr_menu_context',
        'ivr_menu_cid_prefix',
    ];

    protected static function booted()
    {
        static::saving(function ($model) {
            // Remove attributes before saving to database
            unset($model->exit_target_uuid);
            unset($model->exit_action);
            unset($model->exit_action_display);
            unset($model->exit_target_name);
            unset($model->exit_target_extension);
            unset($model->destroy_route);
        });

        static::retrieved(function ($model) {
            if (!empty($model->ivr_menu_exit_app)) {
                $callRoutingOptionsService = new CallRoutingOptionsService();

                $optionDetails = $callRoutingOptionsService->reverseEngineerIVROption($model->ivr_menu_exit_app .' ' . $model->ivr_menu_exit_data);

                if ($optionDetails) {
                    $model->exit_target_uuid = $optionDetails['option'] ?? null;
                    $model->exit_action = $optionDetails['type'] ?? null;
                    $model->exit_action_display = $optionDetails['type'] !== null
                        ? $callRoutingOptionsService->getFriendlyTypeName($optionDetails['type'])
                        : null;
                    $model->exit_target_name = $optionDetails['name'] ?? null;
                    $model->exit_target_extension = $optionDetails['extension'] ?? null;
                }
            }

            $model->destroy_route = route('virtual-receptionists.destroy', $model);

            return $model;
        });
    }

    public function options()
    {
        return $this->hasMany(IvrMenuOptions::class, 'ivr_menu_uuid', 'ivr_menu_uuid');
    }

    public function getId()
    {
        return $this->ivr_menu_extension;
    }

    public function getName()
    {
        return $this->ivr_menu_extension . ' - ' . $this->ivr_menu_name;
    }

        /**
     * Generates a unique sequence number.
     *
     * @return int|null The generated sequence number, or null if unable to generate.
     */
    public function generateUniqueSequenceNumber()
    {
        // Virtual Receptionists will have extensions in the range between 9150 and 9199 by default
        $rangeStart = 9150;
        $rangeEnd = 9199;

        $domainUuid = session('domain_uuid');

        // Fetch all used extensions from Dialplans, Voicemails, and Extensions
        $usedExtensions = Dialplans::where('domain_uuid', $domainUuid)
            ->where('dialplan_number', 'not like', '*%')
            ->pluck('dialplan_number')
            ->merge(
                Voicemails::where('domain_uuid', $domainUuid)
                    ->pluck('voicemail_id')
            )
            ->merge(
                Extensions::where('domain_uuid', $domainUuid)
                    ->pluck('extension')
            )
            ->unique();

        // Find the first available extension
        for ($ext = $rangeStart; $ext <= $rangeEnd; $ext++) {
            if (!$usedExtensions->contains($ext)) {
                // This is your unique extension
                $uniqueExtension = $ext;
                break;
            }
        }

        if (isset($uniqueExtension)) {
            return (string) $uniqueExtension;
        }

        // Return null if unable to generate a unique sequence number
        return null;
    }
}
