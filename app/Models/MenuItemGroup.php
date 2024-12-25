<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuItemGroup extends Model
{
    use HasFactory;

    protected $table = 'v_menu_item_groups';

    public $timestamps = false;

    protected $primaryKey = 'menu_item_group_uuid';

    protected $keyType = 'string';

    // Add guarded or fillable fields based on your preference
    protected $guarded = [];

    // Define the inverse relationship to MenuItem
    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_uuid', 'menu_item_uuid');
    }
}
