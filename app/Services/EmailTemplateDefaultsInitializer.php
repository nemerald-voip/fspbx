<?php

namespace App\Services;

use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class EmailTemplateDefaultsInitializer
{
    public function ensureSeeded(): bool
    {
        if (
            ! Schema::hasTable('email_templates')
            || EmailTemplate::query()->where('template_type', 'default')->exists()
        ) {
            return false;
        }

        $exitCode = Artisan::call('email:templates:seed', [
            '--no-interaction' => true,
        ]);

        if ($exitCode !== 0) {
            $output = trim(Artisan::output());

            throw new RuntimeException(
                $output !== ''
                    ? "Default email template seeding failed:\n{$output}"
                    : 'Default email template seeding failed.'
            );
        }

        return true;
    }
}
