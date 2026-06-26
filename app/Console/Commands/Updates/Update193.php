<?php

namespace App\Console\Commands\Updates;

use App\Models\MenuItem;

class Update193
{
    public function apply(): bool
    {
        $sipProfilesUpdated = MenuItem::query()
            ->where('menu_item_link', '/app/sip_profiles/sip_profiles.php')
            ->update([
                'menu_item_link' => '/sip-profiles',
            ]);

        echo $sipProfilesUpdated === 0
            ? "No SIP Profiles menu items required updating.\n"
            : "Updated {$sipProfilesUpdated} SIP Profiles menu item(s).\n";

        $pinNumbersUpdated = MenuItem::query()
            ->where('menu_item_link', '/app/pin_numbers/pin_numbers.php')
            ->update([
                'menu_item_link' => '/pin-numbers',
            ]);

        echo $pinNumbersUpdated === 0
            ? "No PIN Numbers menu items required updating.\n"
            : "Updated {$pinNumbersUpdated} PIN Numbers menu item(s).\n";

        $databaseTransactionsUpdated = MenuItem::query()
            ->where('menu_item_link', '/app/database_transactions/database_transactions.php')
            ->update([
                'menu_item_link' => '/database-transactions',
            ]);

        echo $databaseTransactionsUpdated === 0
            ? "No Database Transactions menu items required updating.\n"
            : "Updated {$databaseTransactionsUpdated} Database Transactions menu item(s).\n";

        echo "Update 1.9.3 completed successfully.\n";

        return true;
    }
}
