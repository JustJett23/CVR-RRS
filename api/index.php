
<?php

use Illuminate\Http\Request;

require __DIR__.'/../vendor/autoload.php';

$storagePath = '/tmp/cvr-rrs-storage';

$directories = [
    'framework/cache/data',
    'framework/sessions',
    'framework/views',
    'logs',
    'app',
];

foreach ($directories as $directory) {
    $path = $storagePath.'/'.$directory;

    if (! is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

$defaults = [
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    'LARAVEL_STORAGE_PATH' => $storagePath,
    'APP_CONFIG_CACHE' => '/tmp/cvr-rrs-config.php',
    'APP_EVENTS_CACHE' => '/tmp/cvr-rrs-events.php',
    'APP_PACKAGES_CACHE' => '/tmp/cvr-rrs-packages.php',
    'APP_ROUTES_CACHE' => '/tmp/cvr-rrs-routes.php',
    'APP_SERVICES_CACHE' => '/tmp/cvr-rrs-services.php',
    'SESSION_DRIVER' => 'cookie',
    'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'LOG_CHANNEL' => 'stderr',
];

foreach ($defaults as $key => $value) {
    if (getenv($key) === false) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

$app = require __DIR__.'/../bootstrap/app.php';

$app->useStoragePath($storagePath);

$app->handleRequest(
    Request::capture()
);
