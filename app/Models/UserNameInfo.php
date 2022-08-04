<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserNameInfo extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "users_adv_fields";

    public $timestamps = false;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    public const CREATED_AT = 'created_at';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_uuid',
        'first_name',
        'last_name',
    ];

    public function user()
    {
        return $this->belongsTo(User::class,'user_uuid','user_uuid');
    }

}
