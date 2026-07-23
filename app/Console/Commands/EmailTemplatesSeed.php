<?php

namespace App\Console\Commands;

use App\Models\EmailTemplate;
use App\Services\EmailTemplateSourceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EmailTemplatesSeed extends Command
{
    protected $signature = 'email:templates:seed
        {--dry-run : Show changes without writing them}';

    protected $description = 'Seed locked default email templates from existing Laravel email views';

    public function handle(): int
    {
        if (! Schema::hasTable('email_templates')) {
            $this->warn('Skipping email:templates:seed because the email_templates table does not exist.');

            return self::SUCCESS;
        }

        $counts = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];

        foreach (app(EmailTemplateSourceService::class)->definitions() as $definition) {
            try {
                $counts[$this->seedTemplate($definition, (bool) $this->option('dry-run'))]++;
            } catch (\Throwable $exception) {
                $counts['failed']++;
                $this->error('['.$definition['template_key'].'] '.$exception->getMessage());
            }
        }

        $this->info("Email template seed complete. Inserted: {$counts['inserted']}, Updated: {$counts['updated']}, Skipped: {$counts['skipped']}, Failed: {$counts['failed']}.");

        return $counts['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function seedTemplate(array $definition, bool $dryRun): string
    {
        $templateKey = $definition['template_key'];
        $language = $definition['template_language'];
        $checksum = hash('sha256', implode("\n", [
            $definition['template_subject'],
            $definition['template_html'],
            (string) $definition['template_text'],
            $definition['template_layout'],
        ]));

        $existing = EmailTemplate::query()
            ->where('template_type', 'default')
            ->where('template_key', $templateKey)
            ->where('template_language', $language)
            ->first();

        if (! $existing) {
            $this->line(($dryRun ? '[dry]' : '[seed]')." {$templateKey} @ {$definition['version']}");
            if (! $dryRun) {
                EmailTemplate::query()->create([
                    'email_template_uuid' => (string) Str::uuid(),
                    'domain_uuid' => null,
                    'base_template_uuid' => null,
                    'base_version' => null,
                    'template_key' => $templateKey,
                    'template_type' => 'default',
                    'template_language' => $language,
                    'template_category' => $definition['template_category'],
                    'template_subcategory' => $definition['template_subcategory'],
                    'template_layout' => $definition['template_layout'],
                    'version' => $definition['version'],
                    'template_subject' => $definition['template_subject'],
                    'template_html' => $definition['template_html'],
                    'template_text' => $definition['template_text'],
                    'template_enabled' => true,
                    'template_description' => $definition['template_description'],
                    'checksum' => $checksum,
                    'created_by' => null,
                    'updated_by' => null,
                ]);
            }

            return 'inserted';
        }

        if (hash_equals((string) $existing->checksum, $checksum)) {
            $this->line("[skip] {$templateKey} @ {$definition['version']}");

            return 'skipped';
        }

        if (version_compare($definition['version'], (string) $existing->version, '<=')) {
            throw new \RuntimeException(
                "Content changed without a newer version. Installed: {$existing->version}; source: {$definition['version']}."
            );
        }

        $this->line(($dryRun ? '[dry]' : '[update]')." {$templateKey} {$existing->version} -> {$definition['version']}");
        if (! $dryRun) {
            $existing->forceFill([
                'domain_uuid' => null,
                'base_template_uuid' => null,
                'base_version' => null,
                'template_type' => 'default',
                'template_language' => $language,
                'template_category' => $definition['template_category'],
                'template_subcategory' => $definition['template_subcategory'],
                'template_layout' => $definition['template_layout'],
                'version' => $definition['version'],
                'template_subject' => $definition['template_subject'],
                'template_html' => $definition['template_html'],
                'template_text' => $definition['template_text'],
                'template_enabled' => true,
                'template_description' => $definition['template_description'],
                'checksum' => $checksum,
                'updated_by' => null,
            ])->saveQuietly();
        }

        return 'updated';
    }
}
