<?php

class mod_cache
{
    public function __construct(array $args)
    {
        if ($args === [] || in_array('--help', $args, true) || in_array('-h', $args, true) || $args[0] === 'help') {
            echo "Gunakan: php guavas-cmd cache clear [--name=NAMA|--expiry-only|--view]\n";
            return;
        }
        if (array_shift($args) !== 'clear') {
            fwrite(STDERR, "Perintah cache tidak dikenal.\n");
            exit(1);
        }
        $name = null;
        $mode = null;
        foreach ($args as $arg) {
            if (preg_match('/^--name=([A-Za-z0-9_.-]+)$/D', $arg, $match) && $name === null) {
                $name = $match[1];
            } elseif (in_array($arg, ['--expiry-only', '--view'], true) && $mode === null) {
                $mode = $arg;
            } else {
                fwrite(STDERR, "Opsi cache tidak valid: $arg\n");
                exit(1);
            }
        }
        if ($name !== null && $mode !== null) {
            fwrite(STDERR, "Pilih salah satu opsi pembersihan cache.\n");
            exit(1);
        }
        $count = match ($mode) {
            '--expiry-only' => Cache::clearExpiredOnly(),
            '--view' => Cache::clearViews(),
            default => Cache::clear($name ?? '*'),
        };
        echo "Cache dibersihkan: $count berkas.\n";
    }
}
