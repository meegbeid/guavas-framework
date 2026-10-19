<?php

use Core\System\Guavas;
use Core\System\GuavasRegistration;

class mod_system
{
    protected $args = [];
    protected $commandList = [
        'info',
        'config',
        'set',
        'license',
        'key',
        'register',
        'registration',
    ];

    public function __construct(mixed $args)
    {   
        $this->args = $args;
        $this->init();
    }

    protected function init()
    {
        if ($this->args === [] || in_array('--help', $this->args, true) || in_array('-h', $this->args, true) || $this->args[0] === 'help') {
            echo "Gunakan: php guavas-cmd system <info|config|license|key|set|register|registration> [opsi]\n";
            echo "  set NAMA NILAI; register [--label=NAMA]; registration [status|refresh|revoke]\n";
            return;
        }
        if(!empty($this->args)) {
            $key = array_shift($this->args);
            if(in_array($key, $this->commandList, true)) {
                if (in_array($key, ['info', 'config', 'license'], true) && $this->args !== []) {
                    fwrite(STDERR, "$key tidak menerima argumen tambahan.\n");
                    exit(1);
                }
                $methodName = $key . 'Method';
                $this->$methodName();
            } else {
                fwrite(STDERR, "Perintah system tidak dikenal: $key\n");
                exit(1);
            }
        }
    }
    protected function infoMethod()
    {
        $versionPath = ROOT_DIR . 'core/version.json';
        $installed = is_file($versionPath) ? json_decode((string) file_get_contents($versionPath), true) : null;
        $version = is_array($installed) && is_string($installed['version'] ?? null) ? $installed['version'] : GV_SOFTWARE_VERSION;
        echo "\033[34m"."System Version: "."\033[0m"."Guavas Framework v" . $version . "\n";
        echo "\033[34m"."License: "."\033[0m".GV_SOFTWARE_LICENSE."\n";
        echo "\033[34m"."User: "."\033[0m".GV_SOFTWARE_USER."\n";
        echo "\033[34m"."Valid until: "."\033[0m".(GV_SOFTWARE_EXPIRY === '' ? 'No expiry' : GV_SOFTWARE_EXPIRY)."\n";
        echo "\033[34m"."Build-In Webserver: "."\033[0m"."Guavas-HTTP\n" ;
        echo "\033[34m"."APP KEY: " ."\033[0m" . "(disembunyikan)\n\n";
    }
    protected function configMethod()
    {
        echo "\033[34m"."APP_KEY: "."\033[0m" . "(disembunyikan)\n";
        echo "\033[34m"."GV_ENVI: "."\033[0m".GV_ENVI."\n";
        echo "\033[34m"."GV_SESSION_NAME: "."\033[0m".GV_SESSION_NAME."\n";
        echo "\033[34m"."GV_SESSION_DIR: "."\033[0m".GV_SESSION_DIR."\n";
        echo "\033[34m"."GV_LOG_ERROR: "."\033[0m".GV_LOG_ERROR."\n" ;
        echo "\033[34m"."GV_LOG_APP: " ."\033[0m" . GV_LOG_APP . "\n";
        echo "\033[34m"."TEMP_DIR: " ."\033[0m" . TEMP_DIR . "\n";
        echo "\033[34m"."DEFAULT_TIMEZONE: " ."\033[0m" . DEFAULT_TIMEZONE . "\n";
        echo "\033[34m"."DEFAULT_LANGUAGE: " ."\033[0m" . DEFAULT_LANGUAGE . "\n";
        echo "\033[34m"."ENABLE_COMPOSER: " ."\033[0m" . ENABLE_COMPOSER . "\n";
        echo "\033[34m"."ENABLE_FIREWALL: " ."\033[0m" . ENABLE_FIREWALL . "\n";
        echo "\033[34m"."ENABLE_CSRF_VERIFY: " ."\033[0m" . ENABLE_CSRF_VERIFY . "\n";
        echo "\033[34m"."CSRF_EXPIRY: " ."\033[0m" . CSRF_EXPIRY . "\n";
        echo "\033[34m"."ACCESS_CONTROL_ALLOW_ORIGIN: " ."\033[0m" . ACCESS_CONTROL_ALLOW_ORIGIN . "\n";
        echo "\033[34m"."GV_CACHE_DIR: " ."\033[0m" . GV_CACHE_DIR . "\n";
        echo "\033[34m"."GV_CACHE_PREFIX: " ."\033[0m" . GV_CACHE_PREFIX . "\n";
    }
    protected function setMethod()
    {
        $name = $this->args[0] ?? '';
        $value = $this->args[1] ?? null;
        $allowed = ['GV_ENVI', 'DEFAULT_TIMEZONE', 'DEFAULT_LANGUAGE', 'ENABLE_FIREWALL', 'ENABLE_CSRF_VERIFY'];
        if (count($this->args) !== 2 || !in_array($name, $allowed, true)) {
            fwrite(STDERR, "Gunakan: system set <GV_ENVI|DEFAULT_TIMEZONE|DEFAULT_LANGUAGE|ENABLE_FIREWALL|ENABLE_CSRF_VERIFY> <nilai>.\n");
            exit(1);
        }
        if (in_array($name, ['ENABLE_FIREWALL', 'ENABLE_CSRF_VERIFY'], true)) {
            if (!in_array(strtolower($value), ['true', 'false'], true)) {
                fwrite(STDERR, "Nilai harus true atau false.\n");
                exit(1);
            }
            $value = strtolower($value) === 'true';
        } elseif ($name === 'GV_ENVI' && !in_array($value, ['development', 'production'], true)) {
            fwrite(STDERR, "GV_ENVI harus development atau production.\n");
            exit(1);
        } elseif ($name === 'DEFAULT_TIMEZONE' && !in_array($value, timezone_identifiers_list(), true)) {
            fwrite(STDERR, "Zona waktu tidak valid.\n");
            exit(1);
        } elseif ($name === 'DEFAULT_LANGUAGE' && !preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/D', $value)) {
            fwrite(STDERR, "Kode bahasa tidak valid.\n");
            exit(1);
        }
        $path = ROOT_DIR . 'core/system/libs/init.gv';
        $config = @unserialize(gv_decrypt((string) @file_get_contents($path)), ['allowed_classes' => false]);
        if (!is_array($config)) {
            fwrite(STDERR, "Konfigurasi awal tidak dapat dibaca.\n");
            exit(1);
        }
        $config[$name] = $value;
        $temp = tempnam(dirname($path), '.init-');
        if ($temp === false || file_put_contents($temp, gv_encrypt(serialize($config)), LOCK_EX) === false || !@rename($temp, $path)) {
            if (is_string($temp) && is_file($temp)) @unlink($temp);
            fwrite(STDERR, "Gagal menyimpan konfigurasi.\n");
            exit(1);
        }
        echo "$name tersimpan. Jalankan perintah berikutnya untuk melihat nilai baru.\n";
    }
    protected function licenseMethod()
    {
        $validLicense = GV_SOFTWARE_EXPIRY === '' || time() < strtotime(GV_SOFTWARE_EXPIRY) ? 'Valid' : 'Expired';
        echo "Status: " . $validLicense . "\n";
        echo "License: ".GV_SOFTWARE_LICENSE."\n";
        echo "Expired: ".(GV_SOFTWARE_EXPIRY === '' ? 'No expiry' : GV_SOFTWARE_EXPIRY)."\n";
    }
    protected function keyMethod()
    {
        //
        if (count($this->args) > 1) {
            fwrite(STDERR, "Gunakan: system key --generate.\n");
            exit(1);
        }
        $key = array_shift($this->args);
        if($key == '--generate') {
            $guavas = new Guavas;
            $newkey = $guavas->updateKey();
            echo "New App Key: ".$newkey."\n";
            exit;
        } 
        echo "For more information on a specific \"system key\" command:";
		echo "\nphp guavas-cmd system key [--generate]";
		echo "\n\n";
    }

    protected function registerMethod(): void
    {
        try {
            $label = basename(rtrim(ROOT_DIR, '/\\'));
            foreach ($this->args as $argument) {
                if (str_starts_with($argument, '--label=')) {
                    $label = substr($argument, 8);
                } else {
                    throw new RuntimeException('Opsi tidak dikenal. Gunakan --label=NAMA_PROYEK; jangan taruh kunci di argumen.');
                }
            }
            if ($label === '' || strlen($label) > 150) {
                throw new RuntimeException('Label proyek harus berisi 1 sampai 150 karakter.');
            }
            $key = getenv('GUAVAS_LICENSE_KEY');
            if (!is_string($key) || $key === '') {
                if (!defined('STDIN') || !stream_isatty(STDIN)) {
                    throw new RuntimeException('Gunakan terminal interaktif atau variabel lingkungan GUAVAS_LICENSE_KEY.');
                }
                fwrite(STDOUT, 'Kunci customer: ');
                $key = trim((string) fgets(STDIN));
            }
            $versionData = json_decode((string) file_get_contents(ROOT_DIR . 'core/version.json'), true);
            $version = is_array($versionData) && is_string($versionData['version'] ?? null) ? $versionData['version'] : 'unknown';
            $status = (new GuavasRegistration(ROOT_DIR))->register($key, $label, $version);
            echo 'Registrasi berhasil. ID instalasi: ' . $status['installation_id'] . PHP_EOL;
            echo 'Customer: ' . ($status['customer']['name'] ?? '(tidak tersedia)') . PHP_EOL;
        } catch (Throwable $e) {
            fwrite(STDERR, $e->getMessage() . PHP_EOL);
            exit(1);
        }
    }

    protected function registrationMethod(): void
    {
        try {
            $action = array_shift($this->args) ?? 'status';
            if ($this->args !== [] || !in_array($action, ['status', 'refresh', 'revoke'], true)) {
                throw new RuntimeException('Gunakan: system registration [status|refresh|revoke].');
            }
            $registration = new GuavasRegistration(ROOT_DIR);
            if ($action === 'refresh') {
                $registration->refresh();
                echo "Token instalasi berhasil diperbarui.\n";
            } elseif ($action === 'revoke') {
                $registration->revoke();
                echo "Registrasi instalasi berhasil dicabut. Aplikasi tetap dapat berjalan.\n";
            } else {
                $status = $registration->status();
                echo 'ID instalasi: ' . $status['installation_id'] . PHP_EOL;
                echo 'Status: ' . ($status['registered'] ? 'Terdaftar' : 'Belum terdaftar') . PHP_EOL;
                if ($status['registered']) {
                    echo 'Customer: ' . ($status['customer']['name'] ?? '(tidak tersedia)') . PHP_EOL;
                    echo 'Terdaftar sejak: ' . $status['registered_at'] . PHP_EOL;
                }
            }
        } catch (Throwable $e) {
            fwrite(STDERR, $e->getMessage() . PHP_EOL);
            exit(1);
        }
    }

}

class Bar
{

    const MOVE_START = "\033[1G";
    const HIDE_CURSOR = "\033[?25l";
    const SHOW_CURSOR = "\033[?25h";
    const ERASE_LINE = "\033[2K";

    private $reDraw = false;

    /**
     * Available screen width
     *
     * @var int
     */
    private $width;

    /**
     * Ouput stream. Usually STDOUT or STDERR
     *
     * @var false|mixed|resource
     */
    private $stream;

    /**
     * Output string format
     *
     * @var string
     */
    private $format;

    /**
     * Time the progress bar was initialised in seconds (with millisecond precision)
     *
     * @var float|string
     */
    private $startTime;

    /**
     * Time since the last draw
     *
     * @var float|string
     */
    private $timeSinceLastCall;

    /**
     * Pre-defined tokens in the format
     *
     * @var string[]
     */
    private $ouputFind = array(':current', ':total', ':elapsed', ':percent', ':eta', ':rate');

    /**
     * Do not run drawBar more often than this (bypassed by interupt())
     *
     * @var float
     */
    public $throttle = 0.1;

    /**
     * The symbol to denote completed parts of the bar
     *
     * @var string
     */
    public $symbolComplete = "=";

    /**
     * The symbol to denote incomplete parts of the bar
     *
     * @var string
     */
    public $symbolIncomplete = " ";

    /**
     * Number of decimal places to use for seconds units
     *
     * @var int
     */
    public $secondPrecision = 0;

    /**
     * Number of decimal places used for percentage units
     *
     * @var int
     */
    public $percentPrecision = 1;

    /**
     * Current tick number
     *
     * @var int
     */
    public $current = 0;

    /**
     * Maximum number of ticks
     *
     * @var int|mixed
     */
    public $total = 1;

    /**
     * Seconds elapsed
     *
     * @var int
     */
    public $elapsed = 0;

    /**
     * Current percentage complete
     *
     * @var int
     */
    public $percent = 0;

    /**
     * Estimated time until completion
     *
     * @var int
     */
    public $eta = 0;

    /**
     * Current rate
     *
     * @var int
     */
    public $rate = 0;


    public function __construct($total = 1, $format = "Progress: [:bar] - :current/:total - :percent% - Elapsed::elapseds - ETA::etas - Rate::rate/s", mixed $stream = STDERR)
    {
        // Get the terminal width
        $this->reDraw = true;
        if(strcasecmp(substr(PHP_OS, 0, 3), 'WIN') == 0){
            exec('mode con', $output);
		    foreach($output as $ar)
            {
                if(preg_match('~(Columns|columns):([\\w.\ ]+)~', $ar, $matched))
                {
                    $this->width = (int)trim($matched[2]);
                }
            } 
        } else {
            $this->width = exec("tput cols 2>/dev/null");
        }

        if (!is_numeric($this->width)) {
            // Default to 80 columns, mainly for windows users with no tput
            $this->width = 80;
        }

        $this->total = $total;
        $this->format = $format;
        $this->stream = $stream;

        // Initialise the display
        fwrite($this->stream, self::HIDE_CURSOR);
        fwrite($this->stream, self::MOVE_START);

        // Set the start time
        $this->startTime = microtime(true);
        $this->timeSinceLastCall = microtime(true);

        $this->drawBar();
    }

    /**
     * Increment by $amount ticks
     *
     * @param int $amount
     */
    public function tick($amount = 1)
    {
        $this->update($this->current + $amount);
    }

    /**
     * Set the increment and re-calculate data
     *
     * @param int $amount
     */
    public function update($amount)
    {
        $this->current = $amount;
        $drawElapse = microtime(true) - $this->timeSinceLastCall;

        if ($drawElapse > $this->throttle) {
            $this->elapsed = microtime(true) - $this->startTime;
            $this->percent = $this->current / $this->total * 100;
            $this->rate = $this->current / $this->elapsed;
            $this->eta = ($this->current) ? ($this->elapsed / $this->current * $this->total - $this->elapsed) : false;

            $this->drawBar();
        }
    }

    /**
     * Add a message on a newline before the progress bar
     */
    public function interupt(string $message)
    {
        fwrite($this->stream, self::MOVE_START);
        fwrite($this->stream, self::ERASE_LINE);
        fwrite($this->stream, $message . "\n");
        $this->drawBar();
    }

    /**
     * Does the actual drawing
     */
    private function drawBar()
    {
        $this->timeSinceLastCall = microtime(true);
        fwrite($this->stream, self::MOVE_START);
        
        $replace = array(
            $this->current,
            $this->total,
            $this->roundAndPad($this->elapsed, $this->secondPrecision),
            $this->roundAndPad($this->percent, $this->percentPrecision),
            $this->roundAndPad($this->eta, $this->secondPrecision),
            $this->roundAndPad($this->rate),
        );
        if(!$this->reDraw) {
            $output = str_replace($this->ouputFind, $replace, $this->format);
        
            if (strpos($output, ':bar') !== false) {
                $availableSpace = $this->width - strlen($output) + 4;
                $done = $availableSpace * ($this->percent / 100);
                $left = $availableSpace - $done;
                $output = str_replace(':bar', str_repeat($this->symbolComplete, (int)max($done, 0)) . str_repeat($this->symbolIncomplete, (int)max($left, 0)), $output);
            }
        

        fwrite($this->stream, $output);
    } else {
        $this->reDraw = false;
        
    }
    }

    /**
     * Adds 0 and space padding onto floats to ensure the format is fixed length nnn.nn
     *
     * @param float|int $input
     * @param int $precision
     *
     * @return string
     */
    private function roundAndPad($input, $precision = 1)
    {
        return str_pad(number_format($input, $precision, '.', ''), 6, " ", STR_PAD_LEFT);
    }

    /**
     * Cleanup
     */
    public function end()
    {
        fwrite($this->stream, "\n" . self::SHOW_CURSOR);
    }

    public function __destruct()
    {
        $this->end();
    }

}
