<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddExcludeFromStaleReportToMobileAppUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('mobile_app_users', 'exclude_from_stale_report')) {
            Schema::table('mobile_app_users', function (Blueprint $table) {
                $table->boolean('exclude_from_stale_report')->default(false);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('mobile_app_users', 'exclude_from_stale_report')) {
            Schema::table('mobile_app_users', function (Blueprint $table) {
                $table->dropColumn('exclude_from_stale_report');
            });
        }
    }
}
