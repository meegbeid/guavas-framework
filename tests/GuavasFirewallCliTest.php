<?php

declare(strict_types=1);

$root = dirname(__DIR__) . '/storage/guavas-firewall-test-' . bin2hex(random_bytes(6));
define('GV_STORAGE_DIR', $root . '/');
require dirname(__DIR__) . '/core/system/console/mod_gwsf.php';

$directory = GV_STORAGE_DIR . 'gwsf/';
mkdir($directory, 0755, true);
$history = $directory . 'blacklist_ip.gv';
$bans = $directory . 'banned_ip.gv';
$remove = static function (string $path) use (&$remove): void {
    if (!is_dir($path)) return;
    foreach (new FilesystemIterator($path) as $entry) {
        if ($entry->isDir()) $remove($entry->getPathname());
        else unlink($entry->getPathname());
    }
    rmdir($path);
};

try {
    $lines = array_fill(0, 5, '2026-10-19 00:00:00|192.0.2.10|0');
    $lines[] = '2026-10-19 00:00:00|198.51.100.20|1';
    file_put_contents($history, implode(PHP_EOL, $lines) . PHP_EOL);
    ob_start();
    new mod_gwsf(['ipban-update']);
    ob_end_clean();
    if (!str_contains((string) file_get_contents($bans), '192.0.2.10')) throw new RuntimeException('Batas percobaan gagal memblokir IP.');
    ob_start();
    new mod_gwsf(['flush']);
    ob_end_clean();
    $after = (string) file_get_contents($bans);
    if (str_contains($after, '192.0.2.10') || !str_contains($after, '198.51.100.20')) {
        throw new RuntimeException('Flush tidak mempertahankan blokir permanen.');
    }
    ob_start();
    new mod_gwsf(['release', '--ip=198.51.100.20']);
    ob_end_clean();
    if (trim((string) file_get_contents($bans)) !== '' || trim((string) file_get_contents($history)) !== '') {
        throw new RuntimeException('Release tidak melepas IP tepat sasaran.');
    }
    echo "Guavas firewall CLI test passed.\n";
} finally {
    $remove($root);
}
