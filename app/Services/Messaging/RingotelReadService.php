<?php

namespace App\Services\Messaging;

use App\Models\Messages;

/** Compatibility for callers and serialized jobs queued before read sync was retired. */
class RingotelReadService
{
    public function dispatch(Messages $message, ?string $userUuid = null, ?string $authorizedExtensionUuid = null): void
    {
        // Ringotel confirmed that read changes checkmarks, not unread counts.
        // Keep old callers harmless without queuing any replacement work.
    }

    public function sync(string $messageUuid, string $extensionUuid, ?string $userUuid, bool $authorizedViewAs = false): bool
    {
        // Drain already queued SyncRingotelRead jobs without API calls or retries.
        return true;
    }
}
