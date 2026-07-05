<?php

namespace App\Services;

use App\Models\PinNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PinNumberService
{
    public function save(array $validated, ?PinNumber $pinNumber = null): PinNumber
    {
        return DB::transaction(function () use ($validated, $pinNumber) {
            $pinNumber ??= new PinNumber();
            $isNew = ! $pinNumber->exists;

            $pinNumber->forceFill([
                'domain_uuid' => session('domain_uuid'),
                'pin_number_uuid' => $pinNumber->pin_number_uuid ?: (string) Str::uuid(),
                'pin_number' => trim((string) $validated['pin_number']),
                'accountcode' => $this->blankToNull($validated['accountcode'] ?? null),
                'enabled' => $validated['enabled'],
                'description' => $this->blankToNull($validated['description'] ?? null),
                $isNew ? 'insert_date' : 'update_date' => now(),
                $isNew ? 'insert_user' : 'update_user' => session('user_uuid'),
            ])->save();

            return $pinNumber;
        });
    }

    public function toggle(Collection $pinNumbers): void
    {
        DB::transaction(function () use ($pinNumbers) {
            foreach ($pinNumbers as $pinNumber) {
                $pinNumber->forceFill([
                    'enabled' => $pinNumber->enabled === 'true' ? 'false' : 'true',
                    'update_date' => now(),
                    'update_user' => session('user_uuid'),
                ])->save();
            }
        });
    }

    public function delete(Collection $pinNumbers): int
    {
        return DB::transaction(function () use ($pinNumbers) {
            return PinNumber::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('pin_number_uuid', $pinNumbers->pluck('pin_number_uuid'))
                ->delete();
        });
    }

    public function copy(Collection $pinNumbers): int
    {
        return DB::transaction(function () use ($pinNumbers) {
            $count = 0;

            foreach ($pinNumbers as $pinNumber) {
                $copy = $pinNumber->replicate();
                $copy->pin_number_uuid = (string) Str::uuid();
                $copy->description = trim((string) $pinNumber->description . ' (copy)');
                $copy->insert_date = now();
                $copy->insert_user = session('user_uuid');
                $copy->update_date = null;
                $copy->update_user = null;
                $copy->save();
                $count++;
            }

            return $count;
        });
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
