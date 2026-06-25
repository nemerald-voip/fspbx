<?php

namespace App\Console\Commands\Updates;

use App\Models\MenuItem;

class Update193
{
    public function apply(): bool
    {
        $updated = MenuItem::query()
            ->where('menu_item_link', '/app/sip_profiles/sip_profiles.php')
            ->update([
                'menu_item_link' => '/sip-profiles',
            ]);

        echo $updated === 0
            ? "No SIP Profiles menu items required updating.\n"
            : "Updated {$updated} SIP Profiles menu item(s).\n";

        echo "Update 1.9.3 completed successfully.\n";

        return true;
    }
}
