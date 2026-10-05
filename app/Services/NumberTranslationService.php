<?php

namespace App\Services;

use App\Models\FusionCache;
use App\Models\NumberTranslation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SimpleXMLElement;

class NumberTranslationService
{
    public function item(NumberTranslation $translation): array
    {
        return [
            'uuid' => $translation->getKey(),
            'name' => $translation->number_translation_name,
            'description' => $translation->number_translation_description,
            'enabled' => $translation->number_translation_enabled === 'true',
            'rules' => $translation->rules->map(fn ($rule) => [
                'uuid' => $rule->getKey(),
                'regex' => $rule->number_translation_detail_regex,
                'replace' => $rule->number_translation_detail_replace ?? '',
                'order' => $rule->number_translation_detail_order,
            ])->values()->all(),
        ];
    }

    public function save(array $data, ?NumberTranslation $translation = null): NumberTranslation
    {
        return DB::transaction(function () use ($data, $translation) {
            $translation = $translation
                ? NumberTranslation::query()->lockForUpdate()->findOrFail($translation->getKey())
                : new NumberTranslation(['number_translation_uuid' => Str::uuid()->toString()]);
            $translation->forceFill([
                'number_translation_name' => $data['name'],
                'number_translation_description' => $data['description'] ?? null,
                'number_translation_enabled' => $data['enabled'] ? 'true' : 'false',
            ])->save();

            $retained = [];
            foreach ($data['rules'] as $row) {
                // Scope every detail lookup to this profile; retain existing UUIDs.
                $rule = ! empty($row['uuid'])
                    ? $translation->rules()->whereKey($row['uuid'])->firstOrFail()
                    : $translation->rules()->make(['number_translation_detail_uuid' => Str::uuid()->toString()]);
                $rule->forceFill([
                    'number_translation_detail_regex' => $row['regex'],
                    'number_translation_detail_replace' => $row['replace'] ?? '',
                    'number_translation_detail_order' => isset($row['order'])
                        ? str_pad((string) (int) $row['order'], 3, '0', STR_PAD_LEFT) : null,
                ])->save();
                $retained[] = $rule->getKey();
            }
            $translation->rules()->whereNotIn('number_translation_detail_uuid', $retained)->delete();

            return $translation->load('rules');
        });
    }

    public function delete(NumberTranslation $translation): void
    {
        DB::transaction(function () use ($translation) {
            $translation = NumberTranslation::query()->lockForUpdate()->findOrFail($translation->getKey());
            $translation->rules()->delete();
            $translation->delete();
        });
    }

    /** Called only after the database transaction commits, including on retry. */
    public function synchronize(): array
    {
        $esl = null;
        try {
            if (! $this->clearCache()) {
                throw new \RuntimeException(__('Unable to clear the number translation cache.'));
            }
            $esl = app(FreeswitchEslService::class);
            if (! $esl->isConnected()) {
                throw new \RuntimeException(__('FreeSWITCH event socket is unavailable.'));
            }

            // Regenerate and verify the affected configuration before reloadxml
            // tells mod_translate to replace its in-memory profiles.
            $xml = $esl->executeCommand('xml_locate configuration configuration name translate.conf', false);
            if (! $this->configurationMatches($xml)) {
                throw new \RuntimeException(__('Generated number translations do not match the saved rules. Check the XML handler and cache permissions.'));
            }
            $response = $esl->executeCommand('reloadxml', false);
            if (! is_string($response) || ! preg_match('/^\+OK\b/', trim($response))) {
                throw new \RuntimeException(is_string($response) && trim($response) !== ''
                    ? 'FreeSWITCH reloadxml: ' . trim($response)
                    : __('FreeSWITCH XML reload was not confirmed.'));
            }

            return ['synchronized' => true, 'error' => null];
        } catch (\Throwable $exception) {
            logger('Number translation synchronization failed: ' . $exception->getMessage());
            return ['synchronized' => false, 'error' => $exception->getMessage()];
        } finally {
            $esl?->disconnect();
        }
    }

    protected function clearCache(): bool
    {
        return FusionCache::clear('configuration:translate.conf');
    }

    protected function configurationMatches(mixed $xml): bool
    {
        if (! $xml instanceof SimpleXMLElement) {
            return false;
        }
        if ($xml->getName() !== 'configuration' || (string) $xml['name'] !== 'translate.conf') {
            return false;
        }

        $actual = [];
        foreach ($xml->profiles->profile as $profile) {
            $rules = [];
            foreach ($profile->rule as $rule) {
                $rules[] = [(string) $rule['regex'], (string) $rule['replace']];
            }
            $actual[] = [(string) $profile['name'], (string) $profile['description'], $rules];
        }
        $expected = NumberTranslation::query()->where('number_translation_enabled', 'true')
            ->orderBy('number_translation_name')->with('rules')->get()
            ->map(fn ($profile) => [
                $profile->number_translation_name,
                (string) $profile->number_translation_description,
                $profile->rules->filter(fn ($rule) => (string) $rule->number_translation_detail_regex !== '')
                    ->map(fn ($rule) => [(string) $rule->number_translation_detail_regex, (string) $rule->number_translation_detail_replace])
                    ->values()->all(),
            ])->all();

        return $actual === $expected;
    }
}
