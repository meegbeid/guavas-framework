<?php

declare(strict_types=1);

require dirname(__DIR__) . '/core/system/GuavasRegistration.php';

use Core\System\GuavasRegistration;

$root = dirname(__DIR__) . '/storage/guavas-registration-test-' . bin2hex(random_bytes(6));
$calls = [];
$tokenA = str_repeat('A', 43);
$tokenB = str_repeat('B', 43);
$api = static function (string $method, string $url, ?array $body, ?string $token) use (&$calls, $tokenA, $tokenB): array {
    $calls[] = compact('method', 'url', 'body', 'token');
    if (str_ends_with($url, '/registrations/refresh')) {
        return ['status' => 200, 'body' => ['status' => 'success', 'data' => [
            'installation_id' => $body['installation_id'] ?? $calls[0]['body']['installation_id'], 'token' => $tokenB,
        ]]];
    }
    if (str_ends_with($url, '/registrations/revoke')) {
        return ['status' => 200, 'body' => ['status' => 'success', 'code' => 'registration_revoked', 'data' => []]];
    }
    return ['status' => 201, 'body' => ['status' => 'success', 'code' => 'registered', 'data' => [
        'installation_id' => $body['installation_id'], 'token' => $tokenA,
        'customer' => ['id' => null, 'name' => 'Test Customer'],
    ]]];
};

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$remove = static function (string $path) use (&$remove): void {
    if (!is_dir($path)) return;
    foreach (new FilesystemIterator($path) as $entry) {
        if ($entry->isDir()) $remove($entry->getPathname());
        else unlink($entry->getPathname());
    }
    rmdir($path);
};

try {
    $registration = new GuavasRegistration($root, 'https://example.test/api/guavas/v1', $api);
    $id = $registration->installationId();
    $assert($id === $registration->installationId(), 'ID instalasi berubah.');
    $offline = new GuavasRegistration($root, 'http://invalid.example', $api);
    $assert($offline->status()['registered'] === false, 'Status lokal tidak boleh bergantung pada URL API.');
    $assert($registration->status()['registered'] === false, 'Instalasi baru sudah terdaftar.');
    $key = str_repeat('Z', 25);
    $status = $registration->register($key, 'Demo', '0.1.0');
    $assert($status['registered'] && $status['customer']['name'] === 'Test Customer', 'Registrasi gagal.');
    $assert($calls[0]['body']['license_key'] === $key && $calls[0]['body']['installation_id'] === $id, 'Payload registrasi salah.');
    $stored = file_get_contents($root . '/storage/guavas-updates/registration.json');
    $assert(str_contains($stored, $tokenA) && !str_contains($stored, $key), 'Kredensial lokal tidak sesuai.');
    $registration->refresh();
    $assert($calls[1]['token'] === $tokenA, 'Refresh tidak memakai token lama.');
    $assert(str_contains(file_get_contents($root . '/storage/guavas-updates/registration.json'), $tokenB), 'Token baru tidak tersimpan.');
    $registration->revoke();
    $assert($calls[2]['token'] === $tokenB, 'Revoke tidak memakai token baru.');
    $assert($registration->status()['registered'] === false && $registration->installationId() === $id, 'Revoke menghapus identitas instalasi.');
    echo "Guavas registration test passed.\n";
} finally {
    $remove($root);
}
