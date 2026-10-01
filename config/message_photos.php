<?php

return [
    'spool' => env('MESSAGE_PHOTO_SPOOL', storage_path('app/message-photos')),
    'vips_binary' => env('MESSAGE_PHOTO_VIPS_BINARY', '/usr/bin/vips'),
    'vipsheader_binary' => env('MESSAGE_PHOTO_VIPSHEADER_BINARY', '/usr/bin/vipsheader'),
    'nice_binary' => '/usr/bin/nice',
    'prlimit_binary' => '/usr/bin/prlimit',
    'process_timeout' => 15,
    // Address-space limit for each native process, including decoder stacks.
    'process_memory_bytes' => 512 * 1024 * 1024,
    // Total attachment budget, including unchanged files accompanying photos.
    'budget_bytes' => 650000,
    'max_input_bytes' => 50 * 1024 * 1024,
];
