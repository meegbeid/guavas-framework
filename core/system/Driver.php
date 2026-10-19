<?php
namespace Core\System;

class Driver
{   
    protected $arrDefine = [];
    public function __construct()
    {
        $this->initDefine();
    }

    protected function initDefine()
    {
        $this->arrDefine['GV_SERVER_SOFTWARE'] = 'unknown';
        define('SLASH_SEPARATOR', '/');
    }

    public function initSoftware()
    {
        // read software
        if (!empty($_SERVER['SERVER_SOFTWARE'])) {
            if (preg_match('~^(php|apache)~i', $_SERVER['SERVER_SOFTWARE'], $m)) {
                $this->arrDefine['GV_SERVER_SOFTWARE'] = strtolower($m[1]);
            }
        }
    }
    public function initHost()
    {
        // get http host
		$hostName = server('HTTP_HOST');
		$protocol = !server('HTTPS') ? 'http' : 'https';

        // if using cli server
		if(php_sapi_name() == 'cli-server') {
			$baseUrl = $protocol . '://' . $hostName . SLASH_SEPARATOR;
			$baseDir = '/';
		} else {
			$scriptName = pathinfo($_SERVER['SCRIPT_NAME']);
			$baseUrl = $protocol . '://' . $hostName . '/' . slashtrim($scriptName['dirname']);
			$baseDir = '/' . slashtrim($scriptName['dirname']) . SLASH_SEPARATOR;
		}
        
        // set base path
		define('BASE_URL', slashtrim($baseUrl));
        define('BASE_DIR',$baseDir);
    }
    public function loadBootFile()
    {
        $files = glob(ROOT_DIR . "boot/*.php");
        foreach($files as $file)
        {
            file_exists($file) && require_once $file;
        }
    }
}