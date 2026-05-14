<?php

namespace App\Console\Commands\Updates;

use App\Models\MenuItem;

class Update180
{
    public function apply(): bool
    {
        $updated = MenuItem::query()
            ->where('menu_item_link', '/app/modules/modules.php')
            ->update([
                'menu_item_link' => '/modules',
            ]);

        echo $updated === 0
            ? "No Modules menu items required updating.\n"
            : "Updated {$updated} Modules menu item(s).\n";

        echo "Update 1.8.0 completed successfully.\n";

        return true;
    }
}
