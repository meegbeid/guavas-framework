<?php

class mod_migrate
{
    private array $args;
    private string $directory;
    private ?\PDO $db = null;
    private string $table = '';
    private string $lockName = '';

    public function __construct(array $args)
    {
        $this->args = $args;
        $this->directory = ROOT_DIR . 'database';

        try {
            $this->run();
        } catch(\Throwable $e) {
            fwrite(STDERR, 'Migration gagal: ' . $e->getMessage() . PHP_EOL);
            exit(1);
        }
    }

    private function run(): void
    {
        $command = $this->args[0] ?? '';
        if(in_array($command, ['help', '--help', '-h'], true) || in_array('--help', $this->args, true) || in_array('-h', $this->args, true)) {
            $this->showHelp();
            return;
        }

        $files = $this->migrationFiles();
        $this->connect();

        if($command === 'status') {
            if (count($this->args) !== 1) throw new \InvalidArgumentException('status tidak menerima argumen tambahan.');
            $this->showStatus($files);
            return;
        }

        if($command === 'init') {
            if (count($this->args) !== 1) throw new \InvalidArgumentException('init tidak menerima argumen tambahan.');
            $this->withLock(function(): void {
                if($this->ledgerExists()) {
                    echo "Tabel riwayat migration sudah ada.\n";
                    return;
                }
                $this->createLedger();
                echo "Tabel riwayat migration dibuat. File SQL lama belum ditandai.\n";
            });
            return;
        }

        if($command === 'baseline') {
            $target = $this->args[1] ?? '';
            if($target === '') throw new \InvalidArgumentException('Pilih nama migration atau --all untuk baseline.');
            if(count($this->args) !== 2) throw new \InvalidArgumentException('Terlalu banyak argumen untuk baseline.');
            $names = $target === '--all' ? array_keys($files) : [$this->resolveName($files, $target)];
            $this->withLock(function() use ($files, $names): void {
                $this->createLedger();
                $applied = $this->appliedMigrations();
                $this->assertUnchanged($files, $applied);
                foreach($names as $name) {
                    if(isset($applied[$name])) {
                        echo "Sudah tercatat: $name\n";
                        continue;
                    }
                    $this->recordMigration($name, $files[$name]['checksum'], 0, 'baseline');
                    echo "Baseline: $name\n";
                }
            });
            return;
        }

        if(count($this->args) > 1) throw new \InvalidArgumentException('Gunakan migrate help untuk melihat format perintah.');
        $selected = $command === '' ? null : $this->resolveName($files, $command);
        $this->withLock(function() use ($files, $selected): void {
            // Database lama mungkin sudah menjalankan SQL secara manual.
            // Jangan otomatis mengulang seluruh folder tanpa baseline/init yang disengaja.
            if(!$this->ledgerExists() && $selected === null && $files !== []) {
                throw new \RuntimeException(
                    'Riwayat migration belum ada. Gunakan "migrate baseline <nama>" untuk SQL lama, '
                    . '"migrate baseline --all" bila semua sudah diterapkan, atau "migrate init" untuk database baru.'
                );
            }
            $this->createLedger();
            $applied = $this->appliedMigrations();
            $this->assertUnchanged($files, $applied);
            $names = $selected === null ? array_keys($files) : [$selected];
            $batch = $this->nextBatch();
            $count = 0;
            foreach($names as $name) {
                if(isset($applied[$name])) {
                    if($selected !== null) echo "Sudah dijalankan: $name\n";
                    continue;
                }
                $sql = file_get_contents($files[$name]['path']);
                if($sql === false) throw new \RuntimeException("File migration tidak dapat dibaca: $name");
                if($this->checksum($sql) !== $files[$name]['checksum']) {
                    throw new \RuntimeException("File migration berubah saat dijalankan: $name");
                }
                $statements = $this->splitSql($sql);
                if($statements === []) throw new \RuntimeException("File migration kosong: $name");
                echo "Menjalankan: $name\n";
                try {
                    foreach($statements as $statement) $this->db->exec($statement);
                    if($this->db->inTransaction()) {
                        $this->db->rollBack();
                        throw new \RuntimeException('SQL membuka transaksi tetapi tidak menutupnya.');
                    }
                    $this->recordMigration($name, $files[$name]['checksum'], $batch, 'run');
                } catch(\Throwable $e) {
                    if($this->db->inTransaction()) $this->db->rollBack();
                    throw new \RuntimeException(
                        "$name gagal. Sebagian perubahan DDL mungkin sudah terjadi; periksa database sebelum mengulang atau baseline. "
                        . $e->getMessage(), 0, $e
                    );
                }
                echo "Selesai: $name\n";
                $count++;
            }
            if($count === 0 && $selected === null) echo "Tidak ada migration baru.\n";
        });
    }

    private function showHelp(): void
    {
        echo "Perintah migration (database koneksi main, file di database/*.sql):\n";
        echo "  php guavas-cmd migrate status             Lihat status seluruh file SQL\n";
        echo "  php guavas-cmd migrate <nama>             Jalankan satu migration yang belum tercatat\n";
        echo "  php guavas-cmd migrate                    Jalankan semua yang belum tercatat\n";
        echo "  php guavas-cmd migrate init               Buat riwayat kosong untuk database baru\n";
        echo "  php guavas-cmd migrate baseline <nama>    Tandai SQL lama sudah diterapkan\n";
        echo "  php guavas-cmd migrate baseline --all     Tandai semua SQL lama sudah diterapkan\n";
        echo "Nama boleh dengan atau tanpa akhiran .sql. Baseline tidak mengeksekusi SQL.\n";
        echo "Gunakan baseline --all hanya jika semua file sudah dipastikan terpasang di database.\n";
    }

    private function migrationFiles(): array
    {
        if(!is_dir($this->directory)) throw new \RuntimeException('Folder database tidak ditemukan.');
        $files = [];
        foreach(glob($this->directory . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $path) {
            $name = basename($path);
            if(strlen($name) > 191 || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.sql$/', $name)) {
                throw new \RuntimeException("Nama file migration tidak valid: $name");
            }
            $sql = file_get_contents($path);
            if($sql === false) throw new \RuntimeException("File migration tidak dapat dibaca: $name");
            $checksum = $this->checksum($sql);
            $files[$name] = ['path' => $path, 'checksum' => $checksum];
        }
        ksort($files, SORT_STRING);
        return $files;
    }

    private function resolveName(array $files, string $name): string
    {
        if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*(?:\.sql)?$/', $name) || str_contains($name, '..')) {
            throw new \InvalidArgumentException('Nama migration tidak valid. Gunakan nama file tanpa path.');
        }
        $name = str_ends_with($name, '.sql') ? $name : $name . '.sql';
        if(!isset($files[$name])) throw new \InvalidArgumentException("Migration tidak ditemukan: $name");
        return $name;
    }

    private function checksum(string $sql): string
    {
        return hash('sha256', str_replace(["\r\n", "\r"], "\n", $sql));
    }

    private function connect(): void
    {
        $cfg = config('connection.main');
        if(!is_array($cfg) || ($cfg['driver'] ?? '') !== 'mysql') {
            throw new \RuntimeException('Koneksi database main/MySQL tidak tersedia.');
        }
        $prefix = (string)($cfg['prefix'] ?? '');
        if(!preg_match('/^[A-Za-z0-9_]*$/', $prefix)) throw new \RuntimeException('Prefix tabel database tidak valid.');
        $this->table = $prefix . 'guavas_migrations';
        $this->lockName = 'guavas_migrate_' . substr(hash('sha256', (string)$cfg['db']), 0, 48);
        $dsn = 'mysql:host=' . $cfg['host'] . ';port=' . $cfg['port']
            . ';dbname=' . $cfg['db'] . ';charset=' . ($cfg['charset'] ?: 'utf8mb4');
        $this->db = new \PDO($dsn, $cfg['username'], $cfg['password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
        ]);
    }

    private function ledgerExists(): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$this->table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function createLedger(): void
    {
        $table = '`' . $this->table . '`';
        $this->db->exec("CREATE TABLE IF NOT EXISTS $table (
            `migration` VARCHAR(191) NOT NULL PRIMARY KEY,
            `checksum` CHAR(64) NOT NULL,
            `batch` INT UNSIGNED NOT NULL,
            `mode` VARCHAR(10) NOT NULL,
            `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function appliedMigrations(): array
    {
        if(!$this->ledgerExists()) return [];
        $rows = $this->db->query('SELECT migration, checksum, batch, mode, applied_at FROM `' . $this->table . '`')->fetchAll(\PDO::FETCH_ASSOC);
        $result = [];
        foreach($rows as $row) $result[$row['migration']] = $row;
        return $result;
    }

    private function assertUnchanged(array $files, array $applied): void
    {
        foreach($applied as $name => $row) {
            if(!isset($files[$name])) {
                throw new \RuntimeException("File migration yang sudah tercatat hilang: $name. Jangan jalankan file lain sebelum diperiksa.");
            }
            if(!hash_equals($row['checksum'], $files[$name]['checksum'])) {
                throw new \RuntimeException("File migration yang sudah tercatat berubah: $name. Jangan jalankan file lain sebelum diperiksa.");
            }
        }
    }

    private function showStatus(array $files): void
    {
        $applied = $this->appliedMigrations();
        if(!$this->ledgerExists()) echo "Riwayat migration belum dibuat. SQL lama mungkin sudah diterapkan manual.\n";
        foreach($files as $name => $file) {
            $status = !isset($applied[$name]) ? 'PENDING' :
                (hash_equals($applied[$name]['checksum'], $file['checksum']) ? strtoupper($applied[$name]['mode']) : 'CHANGED');
            echo str_pad($status, 10) . $name . "\n";
        }
        foreach($applied as $name => $row) {
            if(!isset($files[$name])) echo str_pad('MISSING', 10) . $name . "\n";
        }
        if($files === []) echo "Tidak ada file .sql di database/.\n";
    }

    private function nextBatch(): int
    {
        return (int)$this->db->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM `' . $this->table . '`')->fetchColumn();
    }

    private function recordMigration(string $name, string $checksum, int $batch, string $mode): void
    {
        $stmt = $this->db->prepare('INSERT INTO `' . $this->table . '` (migration, checksum, batch, mode) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $checksum, $batch, $mode]);
    }

    private function withLock(callable $work): void
    {
        $stmt = $this->db->prepare('SELECT GET_LOCK(?, 10)');
        $stmt->execute([$this->lockName]);
        if((int)$stmt->fetchColumn() !== 1) throw new \RuntimeException('Migration lain sedang berjalan. Coba lagi nanti.');
        try {
            $work();
        } finally {
            $stmt = $this->db->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->execute([$this->lockName]);
        }
    }

    private function splitSql(string $sql): array
    {
        $statements = [];
        $current = '';
        $quote = '';
        $lineComment = false;
        $blockComment = false;
        $length = strlen($sql);
        for($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';
            if($lineComment) {
                if($char === "\n") { $lineComment = false; $current .= "\n"; }
                continue;
            }
            if($blockComment) {
                if($char === '*' && $next === '/') { $blockComment = false; $i++; }
                continue;
            }
            if($quote !== '') {
                $current .= $char;
                if($char === '\\' && $next !== '') { $current .= $next; $i++; continue; }
                if($char === $quote) {
                    if($next === $quote) { $current .= $next; $i++; }
                    else $quote = '';
                }
                continue;
            }
            if($char === '-' && $next === '-' && ($sql[$i + 2] ?? '') !== '' && ctype_space($sql[$i + 2])) {
                $lineComment = true; $i++; $current .= ' '; continue;
            }
            if($char === '#') { $lineComment = true; $current .= ' '; continue; }
            if($char === '/' && $next === '*') {
                if(($sql[$i + 2] ?? '') === '!') throw new \RuntimeException('MySQL executable comment tidak didukung dalam migration.');
                $blockComment = true; $i++; $current .= ' '; continue;
            }
            if($char === "'" || $char === '"' || $char === '`') { $quote = $char; $current .= $char; continue; }
            if($char === ';') {
                $statement = trim($current);
                if($statement !== '') $statements[] = $statement;
                $current = '';
                continue;
            }
            $current .= $char;
        }
        if($quote !== '' || $blockComment) throw new \RuntimeException('SQL memiliki string atau komentar yang belum ditutup.');
        $statement = trim($current);
        if($statement !== '') $statements[] = $statement;
        foreach($statements as $statement) {
            if(preg_match('/^DELIMITER\b|^CREATE\s+(?:DEFINER\s*=\s*\S+\s+)?(?:PROCEDURE|FUNCTION|TRIGGER|EVENT)\b/i', $statement)) {
                throw new \RuntimeException('DELIMITER dan stored routine belum didukung oleh migrate.');
            }
        }
        return $statements;
    }
}
