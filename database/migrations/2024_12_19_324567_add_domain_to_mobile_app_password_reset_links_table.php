<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDomainToMobileAppPasswordResetLinksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('mobile_app_password_reset_links', 'domain')) {
            Schema::table('mobile_app_password_reset_links', function (Blueprint $table) {
                $table->string('domain')->nullable()->after('token');
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
        if (Schema::hasColumn('mobile_app_password_reset_links', 'domain')) {
            Schema::table('mobile_app_password_reset_links', function (Blueprint $table) {
                $table->dropColumn('domain');
            });
        }
    }
}

