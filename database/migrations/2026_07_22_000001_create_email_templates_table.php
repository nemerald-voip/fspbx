<?php

use App\Services\Install\InstallSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        app(InstallSchema::class)->ensureEmailTemplatesSchema();

    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
