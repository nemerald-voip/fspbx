<?php

// Deliberately does not boot Laravel, connect to the DB, or render Blade.
require dirname(__DIR__).'/app/Services/FrontendAssets.php';

try {
    $assets = new App\Services\FrontendAssets(dirname(__DIR__));
    $assets->publish(array_slice($argv, 1), true);
    fwrite(STDOUT, "Local frontend build activated. The next application update installs released assets.\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
}
