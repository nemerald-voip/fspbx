<?php

namespace App\Models;

use App\Models\Extensions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MobileAppUsers extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "mobile_app_users";

    public $timestamps = true;

    protected $primaryKey = 'mobile_app_user_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Get extesnion that this mobile app belongs to 
     */
    public function extension()
    {
        return $this->belongsTo(Extensions::class, 'extension_uuid', 'extension_uuid');
    }

}
