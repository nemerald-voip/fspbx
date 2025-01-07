<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    use HasFactory;

    protected $table = 'v_menu_items';

    public $timestamps = false;

    protected $primaryKey = 'menu_item_uuid';

    protected $keyType = 'string';

    // Add guarded or fillable fields based on your preference
    protected $guarded = [];

    // Define the relationship to the Menu model
    public function menu()
    {
        return $this->belongsTo(Menu::class, 'menu_uuid', 'menu_uuid');
    }

    // Define the relationship to parent menu item
    public function parent()
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_parent_uuid', 'menu_item_uuid');
    }

    // Define the relationship to child menu items
    public function children()
    {
        return $this->hasMany(MenuItem::class, 'menu_item_parent_uuid', 'menu_item_uuid');
    }

    // Define the relationship to menu item groups
    public function groups()
    {
        return $this->hasMany(MenuItemGroup::class, 'menu_item_uuid', 'menu_item_uuid');
    }
}
