<?php

namespace App\Services\Messaging\Data;

class DownloadedMediaData extends MessagingEventData
{
    public function __construct(
        public string $binary,
        public ?string $originalName = null,
        public ?string $mimeType = null,
        public ?int $size = null,
        public ?string $sourceUrl = null,
    ) {}
}