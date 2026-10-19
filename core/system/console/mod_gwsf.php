<?php

class mod_gwsf
{
    private const MAX_ATTEMPTS = 5;
    private string $directory;

    public function __construct(array $args)
    {
        $this->directory = GV_STORAGE_DIR . 'gwsf' . DIRECTORY_SEPARATOR;
        $command = array_shift($args);
        if ($command === null || in_array($command, ['help', '--help', '-h'], true) || in_array('--help', $args, true) || in_array('-h', $args, true)) {
            echo "Gunakan: php guavas-cmd gwsf <blacklist|ipban|ipban-update|add|remove|release|flush> [opsi]\n";
            echo "  add/remove --whitelist=IP atau --blacklist=IP\n";
            echo "  release --ip=IP; flush [--force]\n";
            return;
        }
        try {
            match ($command) {
                'blacklist' => $this->listFile('blacklist_ip.gv', $args),
                'ipban' => $this->listFile('banned_ip.gv', $args),
                'ipban-update' => $this->updateBans($args),
                'add', 'remove' => $this->changeIp($command, $args),
                'release' => $this->release($args),
                'flush' => $this->flush($args),
                default => throw new InvalidArgumentException("Perintah gwsf tidak dikenal: $command"),
            };
        } catch (Throwable $error) {
            fwrite(STDERR, $error->getMessage() . PHP_EOL);
            exit(1);
        }
    }

    private function listFile(string $name, array $args): void
    {
        $this->noArgs($args);
        $lines = $this->readLines($name);
        echo $name === 'blacklist_ip.gv' ? "Riwayat blacklist IP:\n" : "IP terblokir:\n";
        if ($lines === []) echo "(kosong)\n";
        else foreach ($lines as $line) echo $line . PHP_EOL;
    }

    private function changeIp(string $command, array $args): void
    {
        if (count($args) !== 1 || !preg_match('/^--(whitelist|blacklist)=(.+)$/D', $args[0], $match)) {
            throw new InvalidArgumentException("Gunakan $command --whitelist=IP atau --blacklist=IP.");
        }
        $ip = $this->validIp($match[2]);
        $name = $match[1] === 'whitelist' ? 'whitelist_ip.gv' : 'banned_ip.gv';
        $lines = $this->readLines($name);
        if ($command === 'add') {
            if (!in_array($ip, $lines, true)) $lines[] = $ip;
        } else {
            $lines = array_values(array_filter($lines, static fn ($line) => $line !== $ip));
        }
        $this->writeLines($name, $lines);
        echo "IP $ip " . ($command === 'add' ? 'ditambahkan' : 'dihapus') . ".\n";
    }

    private function release(array $args): void
    {
        if (count($args) !== 1 || !preg_match('/^--ip=(.+)$/D', $args[0], $match)) {
            throw new InvalidArgumentException('Gunakan release --ip=ALAMAT_IP.');
        }
        $ip = $this->validIp($match[1]);
        $history = array_values(array_filter($this->readLines('blacklist_ip.gv'), static function ($line) use ($ip): bool {
            $parts = explode('|', $line);
            return ($parts[1] ?? null) !== $ip;
        }));
        $bans = array_values(array_filter($this->readLines('banned_ip.gv'), static fn ($line) => $line !== $ip));
        $this->writeLines('blacklist_ip.gv', $history);
        $this->writeLines('banned_ip.gv', $bans);
        echo "IP $ip dilepas dari blacklist dan daftar blokir.\n";
    }

    private function flush(array $args): void
    {
        if ($args !== [] && $args !== ['--force']) throw new InvalidArgumentException('Gunakan flush [--force].');
        $history = $args === ['--force'] ? [] : array_values(array_filter(
            $this->readLines('blacklist_ip.gv'),
            static fn ($line) => (int) (explode('|', $line)[2] ?? 0) === 1
        ));
        $permanentBans = [];
        foreach ($history as $line) {
            $parts = explode('|', $line);
            $ip = $parts[1] ?? '';
            if (filter_var($ip, FILTER_VALIDATE_IP)) $permanentBans[$ip] = $ip;
        }
        $this->writeLines('blacklist_ip.gv', $history);
        $this->writeLines('banned_ip.gv', array_values($permanentBans));
        echo "Riwayat sementara dan daftar blokir dibersihkan.\n";
    }

    private function updateBans(array $args): void
    {
        $this->noArgs($args);
        $counts = [];
        foreach ($this->readLines('blacklist_ip.gv') as $line) {
            $parts = explode('|', $line);
            $ip = $parts[1] ?? '';
            if (filter_var($ip, FILTER_VALIDATE_IP)) $counts[$ip] = ($counts[$ip] ?? 0) + 1;
        }
        $bans = $this->readLines('banned_ip.gv');
        foreach ($counts as $ip => $count) {
            if ($count >= self::MAX_ATTEMPTS && !in_array($ip, $bans, true)) $bans[] = $ip;
        }
        $this->writeLines('banned_ip.gv', $bans);
        echo "Daftar blokir diperbarui.\n";
    }

    private function readLines(string $name): array
    {
        $path = $this->directory . $name;
        if (!is_file($path)) return [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) throw new RuntimeException("Gagal membaca $name.");
        return array_map('trim', $lines);
    }

    private function writeLines(string $name, array $lines): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0755, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Gagal membuat folder firewall.');
        }
        if (file_put_contents($this->directory . $name, $lines ? implode(PHP_EOL, $lines) . PHP_EOL : '', LOCK_EX) === false) {
            throw new RuntimeException("Gagal menyimpan $name.");
        }
    }

    private function validIp(string $ip): string
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) throw new InvalidArgumentException('Alamat IP tidak valid.');
        return $ip;
    }

    private function noArgs(array $args): void
    {
        if ($args !== []) throw new InvalidArgumentException('Perintah ini tidak menerima argumen tambahan.');
    }
}
