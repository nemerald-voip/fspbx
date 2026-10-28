<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MusicOnHoldSeeder extends Seeder
{
    /** Seed shipped global streams on an empty installation without changing runtime state. */
    public function run(): void
    {
        DB::transaction(function () {
            if (DB::table('v_music_on_hold')->exists()) {
                return;
            }

            $configuration = simplexml_load_file(resource_path('autoload_configs/local_stream.conf'), \SimpleXMLElement::class, LIBXML_NONET);
            if ($configuration === false) {
                throw new \RuntimeException('Unable to read the default Music on Hold streams.');
            }

            foreach ($configuration->directory as $directory) {
                $parameters = [];
                foreach ($directory->param as $parameter) {
                    $parameters[(string) $parameter['name']] = (string) $parameter['value'];
                }

                $row = [
                    'music_on_hold_uuid' => (string) Str::uuid(),
                    'domain_uuid' => null,
                    'music_on_hold_name' => (string) $directory['name'],
                    'music_on_hold_path' => (string) $directory['path'],
                    'insert_date' => now(),
                ];
                foreach (['rate', 'shuffle', 'channels', 'interval', 'timer-name', 'chime-list', 'chime-freq', 'chime-max'] as $name) {
                    $row['music_on_hold_'.str_replace('-', '_', $name)] = $parameters[$name] ?? null;
                }

                DB::table('v_music_on_hold')->insert($row);
            }
        });
    }
}
