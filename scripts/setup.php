<?php

declare(strict_types=1);

$root = dirname(__DIR__) . DIRECTORY_SEPARATOR;
$libDir = $root . 'core/system/libs/';

if (!is_dir($libDir)) {
    fwrite(STDERR, "Folder inisialisasi Guavas tidak ditemukan.\n");
    exit(1);
}

define('ROOT_DIR', $root);
require_once $root . 'core/system/Functions.php';
require_once $root . 'core/system/GuavasRegistration.php';

$write = static function (string $name, string $contents) use ($libDir): void {
    $path = $libDir . $name;
    if (is_file($path)) {
        return;
    }
    if (file_put_contents($path, gv_encrypt($contents), LOCK_EX) === false) {
        throw new RuntimeException("Gagal membuat {$name}.");
    }
};

$config = [
    'ACCESS_CONTROL_ALLOW_ORIGIN' => false,
    'APP_KEY' => bin2hex(random_bytes(32)),
    'CSRF_EXPIRY' => 3600,
    'CSRF_ROUTE_EXCEPT' => [],
    'DEFAULT_LANGUAGE' => 'id',
    'DEFAULT_TIMEZONE' => 'Asia/Jakarta',
    'ENABLE_COMPOSER' => true,
    'ENABLE_CSRF_VERIFY' => true,
    'ENABLE_FIREWALL' => false,
    'GV_CACHE_DIR' => 'storage/cache/',
    'GV_CACHE_PREFIX' => 'guavas_',
    'GV_ENVI' => 'development',
    'GV_LOG_APP' => $root . 'storage/logs/app.log',
    'GV_LOG_ERROR' => $root . 'storage/logs/error.log',
    'GV_SESSION_DIR' => 'storage/sessions/',
    'GV_SESSION_NAME' => 'guavas_session',
    'SESSION_COOKIE_DOMAIN' => '',
    'SESSION_COOKIE_HTTPONLY' => true,
    'SESSION_COOKIE_LIFETIME' => 0,
    'SESSION_COOKIE_PATH' => '/',
    'SESSION_COOKIE_SAMESITE' => 'Lax',
    'SESSION_COOKIE_SECURE' => false,
    'TEMP_DIR' => 'storage/tmp/',
];

$messages = [
    200 => 'OK',
    201 => 'Created',
    400 => 'Bad Request',
    401 => 'Unauthorized',
    403 => 'Forbidden',
    404 => 'Not Found',
    422 => 'Unprocessable Content',
    500 => 'Internal Server Error',
];

try {
    foreach (['storage/cache', 'storage/logs', 'storage/sessions', 'storage/tmp'] as $dir) {
        $path = $root . $dir;
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException("Gagal membuat folder {$dir}.");
        }
    }
    $write('init.gv', serialize($config));
    $write('license.gv', '0.1.0|GPL-3.0-or-later||Community');
    $write('messages.gv', serialize($messages));
    (new \Core\System\GuavasRegistration($root))->installationId();
    echo "Inisialisasi Guavas selesai.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
