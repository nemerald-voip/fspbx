<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DomainSettings extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_domain_settings";

    public $timestamps = false;

    protected $primaryKey = 'domain_setting_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'domain_uuid',
        'app_uuid',
        'domain_setting_category',
        'domain_setting_subcategory',
        'domain_setting_name',
        'domain_setting_value',
        'domain_setting_order',
        'domain_setting_enabled',
        'domain_setting_description',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [

    ];

    /**
     * Get the domain that owns this setting.
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }
}
