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
        'ivr_menu_name',
        'ivr_menu_extension',
        'ivr_menu_description',
        'ivr_menu_greet_long',
        'ivr_menu_enabled'
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
        });

        static::retrieved(function ($model) {
            if (!empty($model->ivr_menu_exit_data)) {
                $callRoutingOptionsService = new CallRoutingOptionsService();

                $optionDetails = $callRoutingOptionsService->reverseEngineerIVROption('transfer ' .$model->ivr_menu_exit_data);

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
}
