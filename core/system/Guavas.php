<?php
namespace Core\System;

require 'Base.php';
require 'Autoload.php';
use Router;

class Guavas extends Base
{
    protected $version = 'unknown';
    protected $license = '';
    protected $expiry = '';
    protected mixed $_args = null;

    const INITFILE = GV_CORE_DIR . 'system/libs/init.gv';
    const LCFILE = GV_CORE_DIR . 'system/libs/license.gv';
    const MSGFILE = GV_CORE_DIR . 'system/libs/messages.gv';

    public function __construct($argv = null)
    {
        parent::__construct();
        $this->_args = $argv;
    }
    public function initialize()
    {
        $this->autoload();
        $this->globalInput();
        $this->globalFunctions();
        $this->lcfile = static::LCFILE;
        $this->initfile = static::INITFILE;
        $this->messagesfile = static::MSGFILE;
        define('WEB_MODE', 'web');
        define('CLI_MODE', 'console');

        $driver = new \Core\System\Driver;
        $driver->initSoftware();
        $driver->initHost();
        $driver->loadBootFile();
    }
    public function run($method = 'web')
    {
        if ($method === CLI_MODE && (
            ($this->_args[1] ?? null) === 'doctor'
            || in_array('--help', $this->_args ?? [], true)
            || in_array('-h', $this->_args ?? [], true)
            || in_array('help', $this->_args ?? [], true)
        )) {
            $this->consoleMethod();
            return;
        }
        $this->readLicense();
        $this->validLicense();
        $this->buildInitDefine();
        $this->buildMessageDefine();

        if(ACCESS_CONTROL_ALLOW_ORIGIN !== false) {
			header("Access-Control-Allow-Origin: " . ACCESS_CONTROL_ALLOW_ORIGIN);
		}
        // init vendor
        if(str2bool(ENABLE_COMPOSER) && is_file(ROOT_DIR . 'vendor/autoload.php')) {
            require ROOT_DIR . 'vendor/autoload.php';
		}

        if($method == WEB_MODE) {
            make_dir(ROOT_DIR . GV_SESSION_DIR, 0755, true);
            ini_set('session.save_path', ROOT_DIR . GV_SESSION_DIR);
            $this->webMethod();
        } elseif($method == CLI_MODE) {
            $this->consoleMethod();
        }
        
    }
    public function buildInitDefine()
    {
        $param = $this->readInit();
        $fileCfgSystem = GV_APP_DIR . 'config/system.php';
		if(is_file($fileCfgSystem)) {
			$cfgSystem = require $fileCfgSystem;
            foreach($cfgSystem as $key=>$value) {
                $param[$key] = $value;
            }
		}
        foreach($param as $key=>$value) {
            define($key, $value);
        }
    }
    public function updateKey()
    {
        $initFile = $this->readGvFile(static::INITFILE);
        $initFile = unserialize($initFile);
        $newkey = $this->buildKey();
        $initFile['APP_KEY'] = $newkey;
        return $newkey;
    }
    protected function autoload()
    {
        // init autoload
        $autoload = new Autoload;
        $autoload->addNamespace('App\Controllers', 'app/controllers');
		$autoload->addNamespace('App\Middlewares', 'app/middlewares');
		$autoload->addNamespace('App\Repositories', 'app/repositories');
        $autoload->addNamespace('App\Models', 'app/models');
        $autoload->addNamespace('App\Views', 'app/views');
		$autoload->addNamespace('App\Libs', 'app/libs');
		$autoload->addNamespace('Core', 'core');
        $autoload->addNamespace('Core\System', 'core/system');
        $autoload->addNamespace('Storage', 'storage');
        $autoload->addNamespace('Storage\Cache', 'storage/cache');
        $autoload->addNamespace('Storage\Logs', 'storage/logs');
		$autoload->app(true);
    }
    protected function webMethod()
    {
        $requestUri = $_SERVER['REQUEST_URI'];
        $currRoute = explode('?', $requestUri);
		$currRoute = array_shift($currRoute);
		define('CURRENT_ROUTE', slashtrim($currRoute));

        // init session start
		if (session_status() == PHP_SESSION_NONE) {
			session_name(GV_SESSION_NAME);
			session_set_cookie_params([
				'lifetime' => SESSION_COOKIE_LIFETIME,
				'path' => SESSION_COOKIE_PATH,
				'domain' => SESSION_COOKIE_DOMAIN,
				'secure' => SESSION_COOKIE_SECURE,
				'httponly' => SESSION_COOKIE_HTTPONLY,
				'samesite' => SESSION_COOKIE_SAMESITE,
			]);
		    session_start();
		}

        // init log
		ini_set('error_log', GV_LOG_ERROR); 
		ini_set('display_errors', GV_ENVI != 'production');
		date_default_timezone_set(DEFAULT_TIMEZONE);

        if(ENABLE_FIREWALL) {

            $GSF = new GWSF;
            $GSF
                ->setLevel('medium')
                ->start();
        }
        
        // call router
        $app = new Router;
        foreach(glob(GV_APP_DIR . 'routes/*.php') as $file){
		    require $file;
		}
        $app->dispatch();
    }
    protected function consoleMethod()
    {
        $moduleName = $this->_args[1] ?? null;
        if(in_array($moduleName, ['--help', '-h', 'help'], true)) {
            $this->showConsoleHelp();
            return true;
        }
        if ($moduleName === 'doctor') {
            if (count($this->_args) === 3 && in_array($this->_args[2], ['--help', '-h', 'help'], true)) {
                echo "Gunakan: php guavas-cmd doctor\n";
                return true;
            }
            if (count($this->_args) > 2) {
                fwrite(STDERR, "doctor tidak menerima argumen tambahan.\n");
                exit(1);
            }
            $this->showDoctor();
            return true;
        }
        $commandList = [
            'system',
            'http',
            'cache',
            'route',
            'gwsf',
            'migrate'
        ];
        if(is_array($this->_args) && count($this->_args) > 1) {
            array_shift($this->_args);
			$moduleName = $this->_args[0];
			if(in_array($moduleName, $commandList)) {
				array_shift($this->_args);
                require GV_CORE_DIR . 'system/console/mod_' . $moduleName . '.php';
                $className = 'mod_' . $moduleName;
                new $className($this->_args);
				return true;
			}	
		}
        echo "Perintah tidak dikenal" . ($moduleName ? ": $moduleName" : '') . ". Jalankan php guavas-cmd --help.\n";
        exit(1);
    }

    protected function showConsoleHelp()
    {
        echo "Penggunaan: php guavas-cmd <perintah> [argumen] [opsi]\n\n";
        echo "Perintah tersedia:\n";
        echo "  system   Lihat informasi, konfigurasi, dan lisensi Guavas.\n";
          echo "           Subperintah: info, config, set, license, key --generate, register [--label=NAMA].\n";
          echo "           Registrasi: registration [status|refresh|revoke].\n";
        echo "           key --generate hanya menampilkan kunci baru; tidak menyimpannya.\n";
        echo "  http     Jalankan server PHP lokal (default 127.0.0.1:8080).\n";
          echo "           Opsi: --ip=ALAMAT --port=PORT --dir=FOLDER [--force-index].\n";
        echo "  cache    Bersihkan cache aplikasi.\n";
        echo "           Subperintah: clear [--name=NAMA|--expiry-only|--view].\n";
        echo "  route    Panggil method controller dari command line.\n";
        echo "           Format: route Controller@method [--opsi=nilai].\n";
        echo "  gwsf     Lihat dan kelola daftar IP firewall Guavas.\n";
        echo "           Subperintah: blacklist, ipban, add, remove, release, flush, ipban-update.\n";
        echo "  migrate  Lihat status, jalankan, atau tandai SQL migration database.\n";
        echo "           Subperintah: status, <nama>, init, baseline <nama|--all>.\n\n";
          echo "  doctor   Periksa kebutuhan dasar dan struktur proyek (tanpa mengubah data).\n\n";
        echo "Bantuan: php guavas-cmd --help | -h | help\n";
        echo "Contoh:  php guavas-cmd migrate --help\n";
        echo "         php guavas-cmd migrate status\n";
        echo "         php guavas-cmd cache clear --view\n";
    }

      protected function showDoctor(): void
      {
          $checks = [
              'PHP 8.2+' => PHP_VERSION_ID >= 80200,
              'OpenSSL' => extension_loaded('openssl'),
              'Folder public_html' => is_dir(ROOT_DIR . 'public_html'),
              'File route' => is_file(ROOT_DIR . 'app/routes/web.php'),
              'Konfigurasi awal' => is_file(ROOT_DIR . 'core/system/libs/init.gv'),
              'Folder storage' => is_dir(ROOT_DIR . 'storage'),
          ];
          $failed = false;
          foreach ($checks as $label => $ok) {
              echo ($ok ? '[OK] ' : '[PERIKSA] ') . $label . PHP_EOL;
              if (!$ok) $failed = true;
          }
          if ($failed) echo "Periksa item di atas sebelum menjalankan aplikasi.\n";
      }

    protected function buildKey()
    {
        $key = \Hash::random(64);
        return $key;
    }

    protected function validLicense()
    {
        if(!empty($this->expiry)) {
            if(strtotime($this->expiry) < time()) {
                echo "Your license has expired!";
                exit();
            }
        }
        return true;
    }
    protected function buildMessageDefine()
    {
        $param = $this->readMessages();
        define('HTTP_MESSAGES', $param);
    }

}
