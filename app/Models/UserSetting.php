<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_user_settings";

    public $timestamps = false;

    protected $primaryKey = 'user_setting_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_setting_uuid',
        'domain_uuid',
        'user_uuid',
        'user_setting_category',
        'user_setting_subcategory',
        'user_setting_name',
        'user_setting_value',
        'user_setting_enabled',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user',
    ];

    public function user()
    {
        return $this->belongsTo(User::class,'user_uuid','user_uuid');
    }

}
