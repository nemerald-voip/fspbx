<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;

class Dialplans extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_dialplans";

    public $timestamps = false;

    protected $primaryKey = 'dialplan_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'app_uuid',
        'dialplan_name',
        'dialplan_destination',
        'dialplan_number',
        'dialplan_context',
        'dialplan_continue',
        'dialplan_xml',
        'dialplan_order',
        'dialplan_enabled',
        'dialplan_description',
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
     * Get the dialplan details this Dialplan object associated with.
     *  returns Eloquent Object
     */
    public function dialplan_details()
    {
        return $this->hasMany(DialplanDetails::class,'dialplan_uuid','dialplan_uuid');
    }

    public function fax()
    {
        return $this->hasOne(Faxes::class,'dialplan_uuid','dialplan_uuid');
    }

    /**
     * Force to use it, cause laravel's casting method doesn't determine string 'false' as a valid boolean value.
     *
     * @param  string|null  $value
     * @return bool
     */
    public function getDialplanEnabledAttribute(?string $value): bool
    {
        return $value === 'true';
    }

    /**
     * Set the dialplan_enabled attribute.
     *
     * @param  bool $value
     * @return void
     */
    public function setDialplanEnabledAttribute($value): void
    {
        $this->attributes['dialplan_enabled'] = $value ? 'true' : 'false';
    }
}
