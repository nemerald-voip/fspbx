<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuLanguage extends Model
{
    use HasFactory;

    protected $table = 'v_menu_languages';

    public $timestamps = false;

    protected $primaryKey = 'menu_language_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    // Relationships
    public function menu()
    {
        return $this->belongsTo(Menu::class, 'menu_uuid', 'menu_uuid');
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_uuid', 'menu_item_uuid');
    }
}
