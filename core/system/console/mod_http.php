<?php

class mod_http
{
    public function __construct(array $args)
    {
        if (in_array('--help', $args, true) || in_array('-h', $args, true) || $args === ['help']) {
            echo "Gunakan: php guavas-cmd http [--ip=127.0.0.1] [--port=8080] [--dir=public_html] [--force-index]\n";
            return;
        }
        $options = ['ip' => '127.0.0.1', 'port' => '8080', 'dir' => 'public_html'];
        $forceIndex = false;
        foreach ($args as $arg) {
            if ($arg === '--force-index') {
                $forceIndex = true;
            } elseif (preg_match('/^--(ip|port|dir)=(.+)$/D', $arg, $match)) {
                $options[$match[1]] = $match[2];
            } else {
                fwrite(STDERR, "Opsi HTTP tidak dikenal: $arg\n");
                exit(1);
            }
        }
        if (!filter_var($options['ip'], FILTER_VALIDATE_IP) || !ctype_digit($options['port'])
            || (int) $options['port'] < 1 || (int) $options['port'] > 65535) {
            fwrite(STDERR, "IP atau port tidak valid.\n");
            exit(1);
        }
        $root = realpath(ROOT_DIR . 'public_html');
        $dir = realpath(ROOT_DIR . $options['dir']);
        if ($dir === false || $root === false || !str_starts_with(strtolower($dir . DIRECTORY_SEPARATOR), strtolower($root . DIRECTORY_SEPARATOR))) {
            fwrite(STDERR, "Folder HTTP harus ada di dalam public_html.\n");
            exit(1);
        }
        $command = [PHP_BINARY, '-S', $options['ip'] . ':' . $options['port'], '-t', $dir];
        if ($forceIndex) {
            $index = $dir . DIRECTORY_SEPARATOR . 'index.php';
            if (!is_file($index)) {
                fwrite(STDERR, "index.php tidak ditemukan di folder HTTP.\n");
                exit(1);
            }
            $command[] = $index;
        }
        echo "Server lokal berjalan di http://{$options['ip']}:{$options['port']}/. Tekan Ctrl+C untuk berhenti.\n";
        $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, ROOT_DIR);
        if (!is_resource($process)) {
            fwrite(STDERR, "Gagal menjalankan server lokal.\n");
            exit(1);
        }
        $status = proc_close($process);
        if ($status !== 0) exit($status);
    }
}
