<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;

class DialplanDetails extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_dialplan_details";

    public $timestamps = false;

    protected $primaryKey = 'dialplan_detail_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'dialplan_uuid',
        'dialplan_detail_uuid',
        'dialplan_detail_tag',
        'dialplan_detail_type',
        'dialplan_detail_data',
        'dialplan_detail_break',
        'dialplan_detail_inline',
        'dialplan_detail_group',
        'dialplan_detail_order',
        'dialplan_detail_enabled',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct();
        $this->attributes['domain_uuid'] = Session::get('domain_uuid');
        $this->attributes['insert_date'] = date('Y-m-d H:i:s');
        $this->attributes['insert_user'] = Session::get('user_uuid');
        $this->fill($attributes);
    }

    /**
     * Force to use it, cause laravel's casting method doesn't determine string 'false' as a valid boolean value.
     *
     * @param  string|null  $value
     * @return bool
     */
    public function getDialplanDetailEnabledAttribute(?string $value): bool
    {
        return $value === 'true';
    }

    /**
     * Set the dialplan_detail_enabled attribute.
     *
     * @param  bool $value
     * @return void
     */
    public function setDialplanDetailEnabledAttribute($value): void
    {
        $this->attributes['dialplan_detail_enabled'] = $value ? 'true' : 'false';
    }
}
