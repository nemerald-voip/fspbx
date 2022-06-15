<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExtensionUser extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_extension_users";

    public $timestamps = false;

    protected $primaryKey = 'extension_user_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'extension_user_uuid',
        'domain_uuid',
        'extension_uuid',
        'user_uuid',
    ];


    public function user()
    {
        return $this->belongsTo(User::class,'user_uuid','user_uuid');
    }
}
