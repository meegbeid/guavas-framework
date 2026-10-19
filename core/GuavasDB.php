<?php

class GuavasDB
{
    protected string $basePath;

    protected ?string $currentTable = null;
    protected array $wheres = [];
    protected ?int $limit = null;
    protected ?string $orderBy = null;
    protected string $orderDir = 'ASC';

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->initDirs();
    }

    protected function initDirs(): void
    {
        foreach (['tables', 'meta', 'locks'] as $dir) {
            @mkdir($this->basePath . '/' . $dir, 0775, true);
        }

        $meta = $this->basePath . '/meta/tables.json';
        if (!file_exists($meta)) {
            file_put_contents($meta, json_encode([], JSON_PRETTY_PRINT));
        }
    }

    /* =========================
     * TABLE MANAGEMENT
     * ========================= */

    public function createTable(string $table, array $schema): void
    {
        $this->validateTableName($table);

        if (file_exists($this->schemaFile($table))) {
            throw new Exception("Table '$table' already exists");
        }

        $this->validateSchema($schema);

        file_put_contents($this->schemaFile($table), json_encode([
            'table' => $table,
            'created_at' => date('c'),
            'columns' => $schema,
        ], JSON_PRETTY_PRINT));

        file_put_contents($this->dataFile($table), '');
        file_put_contents($this->indexFile($table), json_encode([], JSON_PRETTY_PRINT));

        $this->registerTable($table);
    }

    public function dropTable(string $table): void
    {
        @unlink($this->schemaFile($table));
        @unlink($this->dataFile($table));
        @unlink($this->indexFile($table));

        $metaFile = $this->basePath . '/meta/tables.json';
        $meta = json_decode(file_get_contents($metaFile), true);
        unset($meta[$table]);
        file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT));
    }

    /* =========================
     * QUERY BUILDER
     * ========================= */

    public function table(string $table): self
    {
        $this->currentTable = $table;
        $this->wheres = [];
        $this->limit = null;
        return $this;
    }

    public function where(string $column, $value): self
    {
        $this->wheres[] = [$column, $value];
        return $this;
    }

    public function whereRange(string $column, string $op, $value): self
    {
        $allowed = ['>','>=','<','<='];
        if (!in_array($op, $allowed)) {
            throw new Exception("Invalid range operator");
        }

        $this->wheres[] = [$column, $op, $value];
        return $this;
    }


    public function limit(int $n): self
    {
        $this->limit = $n;
        return $this;
    }

    /* =========================
     * SELECT
     * ========================= */

    public function get(): array
    {
        $this->assertTable();
        $schema = $this->loadSchema($this->currentTable);
        $indexes = $this->loadIndex($this->currentTable);

        $results = [];
        $usedIndex = false;

        // Try index
        foreach ($this->wheres as $cond) {
            if (count($cond) === 2) {
                [$col, $val] = $cond;
                if (($row[$col] ?? null) != $val) {
                    $ok = false;
                    break;
                }
            }

            if (count($cond) === 3) {
                [$col, $op, $val] = $cond;
                $cell = $row[$col] ?? null;

                switch ($op) {
                    case '>':  if (!($cell >  $val)) $ok = false; break;
                    case '>=': if (!($cell >= $val)) $ok = false; break;
                    case '<':  if (!($cell <  $val)) $ok = false; break;
                    case '<=': if (!($cell <= $val)) $ok = false; break;
                }

                if (!$ok) break;
            }
        }


        // Fallback full scan
        if (!$usedIndex) {
            $results = $this->fullScan();
        }

        // Apply where filters
        $filtered = [];
        foreach ($results as $row) {
            if (!empty($row['_deleted'])) continue;

            $ok = true;
            foreach ($this->wheres as [$col, $val]) {
                if (($row[$col] ?? null) != $val) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $filtered[] = $row;
                if ($this->limit && count($filtered) >= $this->limit) {
                    break;
                }
            }
        }

        if ($this->orderBy) {
            $col = $this->orderBy;
            $dir = $this->orderDir;

            usort($filtered, function($a, $b) use ($col, $dir) {
                $va = $a[$col] ?? null;
                $vb = $b[$col] ?? null;

                if ($va == $vb) return 0;

                if ($dir === 'ASC') {
                    return ($va < $vb) ? -1 : 1;
                } else {
                    return ($va > $vb) ? -1 : 1;
                }
            });
        }

        return $filtered;
    }

    public function first(): ?array
    {
        $this->limit(1);
        $res = $this->get();
        return $res[0] ?? null;
    }

    public function count(): int
    {
        return count($this->get());
    }

    /* =========================
     * INSERT
     * ========================= */

    public function insert(array $row): void
    {
        $this->assertTable();
        $schema = $this->loadSchema($this->currentTable);

        $validated = $this->validateRow($schema['columns'], $row);
        $validated['_id'] = $this->generateId();
        $validated['_deleted'] = false;

        $line = json_encode($validated) . PHP_EOL;

        $fp = fopen($this->dataFile($this->currentTable), 'ab');
        flock($fp, LOCK_EX);
        fwrite($fp, $line);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        $this->updateIndexes($this->currentTable, $validated);
    }

    /* =========================
     * UPDATE (TOMBSTONE STYLE)
     * ========================= */

    public function update(array $data): int
    {
        $rows = $this->get();
        $count = 0;

        foreach ($rows as $row) {
            $this->softDeleteById($row['_id']);
            $new = array_merge($row, $data);
            unset($new['_id'], $new['_deleted']);
            $this->insert($new);
            $count++;
        }

        return $count;
    }

    /* =========================
     * DELETE (SOFT)
     * ========================= */

    public function delete(): int
    {
        $rows = $this->get();
        $count = 0;

        foreach ($rows as $row) {
            $this->softDeleteById($row['_id']);
            $count++;
        }

        return $count;
    }

    public function orderBy(string $column, string $dir = 'ASC'): self
    {
        $this->orderBy = $column;
        $this->orderDir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
        return $this;
    }


    protected function softDeleteById(string $id): void
    {
        $fp = fopen($this->dataFile($this->currentTable), 'ab');
        flock($fp, LOCK_EX);
        fwrite($fp, json_encode(['_id'=>$id,'_deleted'=>true,'_tombstone'=>true]) . PHP_EOL);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    /* =========================
     * INDEX SYSTEM
     * ========================= */

    public function createIndex(string $table, string $column, bool $unique = false): void
    {
        $schema = $this->loadSchema($table);
        if (!isset($schema['columns'][$column])) {
            throw new Exception("Column not found");
        }

        $index = $this->loadIndex($table);

        $index[$column] = [
            'unique' => $unique,
            'map' => []
        ];

        $fp = fopen($this->dataFile($table), 'rb');
        while (($line = fgets($fp)) !== false) {
            $row = json_decode($line, true);
            if (!empty($row['_deleted'])) continue;
            if (!isset($row[$column])) continue;

            $key = (string)$row[$column];

            if ($unique && isset($index[$column]['map'][$key])) {
                throw new Exception("Unique index violation on $column = $key");
            }

            $index[$column]['map'][$key][] = $row['_id'];
        }
        fclose($fp);

        file_put_contents($this->indexFile($table), json_encode($index, JSON_PRETTY_PRINT));
    }

    protected function updateIndexes(string $table, array $row): void
    {
        $index = $this->loadIndex($table);

        foreach ($index as $col => &$cfg) {
            $val = $row[$col] ?? null;
            if ($val === null) continue;

            $key = (string)$val;

            if ($cfg['unique'] && isset($cfg['map'][$key])) {
                throw new Exception("Unique index violation on $col = $key");
            }

            $cfg['map'][$key][] = $row['_id'];
        }

        file_put_contents($this->indexFile($table), json_encode($index, JSON_PRETTY_PRINT));
    }


    /* =========================
     * SCAN METHODS
     * ========================= */

    protected function fullScan(): array
    {
        $rows = [];
        $fp = fopen($this->dataFile($this->currentTable), 'rb');
        while (($line = fgets($fp)) !== false) {
            $row = json_decode($line, true);
            if (!$row) continue;
            $rows[$row['_id']] = $row; // last-write-wins
        }
        fclose($fp);
        return array_values($rows);
    }

    protected function scanByIds(array $ids): array
    {
        $set = array_flip($ids);
        $rows = [];

        $fp = fopen($this->dataFile($this->currentTable), 'rb');
        while (($line = fgets($fp)) !== false) {
            $row = json_decode($line, true);
            if (!$row) continue;
            if (isset($set[$row['_id']])) {
                $rows[$row['_id']] = $row;
            }
        }
        fclose($fp);

        return array_values($rows);
    }

    /* =========================
     * VALIDATION & UTILS
     * ========================= */

    protected function validateSchema(array $schema): void
    {
        foreach ($schema as $col => $def) {
            if (!isset($def['type'])) {
                throw new Exception("Type required for $col");
            }
        }
    }

    protected function validateRow(array $columns, array $row): array
    {
        $out = [];
        foreach ($columns as $col => $def) {
            $val = $row[$col] ?? null;
            if ($val === null) {
                if (!empty($def['required'])) {
                    throw new Exception("$col required");
                }
                $out[$col] = null;
                continue;
            }
            $out[$col] = $this->castType($def['type'], $val);
        }
        return $out;
    }

    protected function castType(string $type, mixed $value)
    {
        return match ($type) {
            'int' => (int)$value,
            'float' => (float)$value,
            'bool' => (bool)$value,
            'string' => (string)$value,
            'datetime' => date('c', strtotime($value)),
            default => throw new Exception("Invalid type"),
        };
    }

    protected function loadSchema(string $table): array
    {
        return json_decode(file_get_contents($this->schemaFile($table)), true);
    }

    protected function loadIndex(string $table): array
    {
        return json_decode(file_get_contents($this->indexFile($table)), true) ?: [];
    }

    protected function registerTable(string $table): void
    {
        $file = $this->basePath . '/meta/tables.json';
        $meta = json_decode(file_get_contents($file), true);
        $meta[$table] = ['created_at'=>date('c')];
        file_put_contents($file, json_encode($meta, JSON_PRETTY_PRINT));
    }

    protected function generateId(): string
    {
        return bin2hex(random_bytes(8));
    }

    protected function validateTableName(string $name): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new Exception("Invalid table name");
        }
    }

    protected function assertTable(): void
    {
        if (!$this->currentTable) {
            throw new Exception("No table selected");
        }
    }

    protected function schemaFile(string $table): string
    {
        return "{$this->basePath}/tables/{$table}.schema.json";
    }

    protected function dataFile(string $table): string
    {
        return "{$this->basePath}/tables/{$table}.data.jsonl";
    }

    protected function indexFile(string $table): string
    {
        return "{$this->basePath}/tables/{$table}.index.json";
    }
}
