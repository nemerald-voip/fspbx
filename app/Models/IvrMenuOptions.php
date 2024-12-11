<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IvrMenuOptions extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = 'v_ivr_menu_options';

    public $timestamps = false;

    protected $primaryKey = 'ivr_menu_option_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /*
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ivr_menu_uuid',
        'domain_uuid',
        'ivr_menu_option_digits',
        'ivr_menu_option_action',
        'ivr_menu_option_param',
        'ivr_menu_option_order',
        'ivr_menu_option_description',
        'ivr_menu_option_enabled'
    ];

    // Define the relationship to the IvrMenus model
    public function ivrMenu()
    {
        return $this->belongsTo(IvrMenus::class, 'ivr_menu_uuid', 'ivr_menu_uuid');
    }
}
