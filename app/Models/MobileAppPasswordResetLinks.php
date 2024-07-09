<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property $extension_uuid
 * @property $token
 * @property $created_at
 */
class MobileAppPasswordResetLinks extends Model
{
    use HasFactory, TraitUuid;

    protected $table = "mobile_app_password_reset_links";

    public $timestamps = false;

    protected $primaryKey = 'link_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'extension_uuid',
        'token',
        'created_at'
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct();
        $this->attributes['created_at'] = date('Y-m-d H:i:s');
        $this->fill($attributes);
    }
}
