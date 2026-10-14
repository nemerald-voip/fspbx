<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('paging_groups')) {
            Schema::create('paging_groups', function (Blueprint $table) {
                $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('domain_uuid')->index();
                $table->uuid('dialplan_uuid')->nullable();
                $table->string('name');
                // The number dialed to page the group.
                $table->string('extension', 50);
                // skip: leave phones already on a call alone; force: page them too.
                $table->string('busy_mode', 10)->default('skip');
                // live: the caller talks to the phones; recorded: the caller records, then it is played.
                $table->string('mode', 10)->default('live');
                // Played to the phones before the page.
                $table->boolean('intro_beep')->default(false);
                $table->string('intro_recording')->nullable();
                $table->boolean('enabled')->default(true);
                $table->text('description')->nullable();
                $table->timestamps();

                $table->unique(['domain_uuid', 'extension']);
            });
        }

        if (!Schema::hasTable('paging_group_extensions')) {
            Schema::create('paging_group_extensions', function (Blueprint $table) {
                $table->uuid('uuid')->primary()->default(DB::raw('uuid_generate_v4()'));
                $table->uuid('paging_group_uuid')->index();
                $table->uuid('extension_uuid')->index();
                // member: is paged; caller: may page the group (none means anyone in the account).
                $table->string('role', 10);
                $table->timestamps();

                $table->unique(['paging_group_uuid', 'extension_uuid', 'role']);
                $table->foreign('paging_group_uuid')->references('uuid')->on('paging_groups')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('paging_group_extensions');
        Schema::dropIfExists('paging_groups');
    }
};
