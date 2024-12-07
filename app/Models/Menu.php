<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use HasFactory;

    protected $table = 'v_menus';

    public $timestamps = false;

    protected $primaryKey = 'menu_uuid';

    protected $keyType = 'string';

    // Add guarded or fillable fields based on your preference
    protected $guarded = [];

    // Define the relationship to MenuItems
    public function items()
    {
        return $this->hasMany(MenuItem::class, 'menu_uuid', 'menu_uuid');
    }
}
