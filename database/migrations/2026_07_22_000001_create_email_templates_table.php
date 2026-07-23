<?php

use App\Services\Install\InstallSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        app(InstallSchema::class)->ensureEmailTemplatesSchema();

        $exitCode = Artisan::call('email:templates:seed', [
            '--no-interaction' => true,
        ]);

        if ($exitCode !== 0) {
            $output = trim(Artisan::output());

            throw new \RuntimeException(
                $output !== ''
                    ? "Default email template seeding failed:\n{$output}"
                    : 'Default email template seeding failed.'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
