<?php

return [
    'socket' => env('MESSAGE_PHOTO_SOCKET', '/run/fspbx-photo-compression/worker.sock'),
    'spool' => env('MESSAGE_PHOTO_SPOOL', '/var/lib/fspbx-photo-compression'),
    // Total attachment budget, including unchanged files accompanying photos.
    'budget_bytes' => 650000,
    'max_input_bytes' => 50 * 1024 * 1024,
];
