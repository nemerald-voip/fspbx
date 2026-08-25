<?php

namespace App\Services;

use Throwable;

class FreeswitchGlobalVariableResolver
{
    public function resolve(mixed $value): ?string
    {
        $value = trim((string) $value);

        if (! preg_match('/^\$\$\{([A-Za-z0-9_.-]+)\}$/D', $value, $matches)) {
            return $value;
        }

        try {
            $esl = app(FreeswitchEslService::class);

            if (! $esl->isConnected()) {
                return null;
            }

            $resolved = $esl->executeCommand('global_getvar ' . $matches[1]);
        } catch (Throwable) {
            return null;
        }

        if (! is_scalar($resolved)) {
            return null;
        }

        $resolved = trim((string) $resolved);

        if ($resolved === '' || $resolved === '_undef_' || str_starts_with($resolved, '-ERR')) {
            return null;
        }

        return $resolved;
    }
}
