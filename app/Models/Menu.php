<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = 'v_menus';

    public $timestamps = false;

    protected $primaryKey = 'menu_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    // Add guarded or fillable fields based on your preference
    protected $guarded = [];

    // Define the relationship to MenuItems
    public function items()
    {
        return $this->hasMany(MenuItem::class, 'menu_uuid', 'menu_uuid');
    }

    // Define the relationship to MenuItems
    public function languages()
    {
        return $this->hasMany(MenuLanguage::class, 'menu_uuid', 'menu_uuid');
    }
}
