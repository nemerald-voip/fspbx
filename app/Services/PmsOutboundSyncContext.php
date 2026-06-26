<?php

namespace App\Services;

class PmsOutboundSyncContext
{
    private int $suppressionDepth = 0;

    public function withoutOutboundSync(callable $callback): mixed
    {
        $this->suppressionDepth++;

        try {
            return $callback();
        } finally {
            $this->suppressionDepth = max(0, $this->suppressionDepth - 1);
        }
    }

    public function suppressed(): bool
    {
        return $this->suppressionDepth > 0;
    }
}
