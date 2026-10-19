<?php

/**
 * Guavas Web Security and Firewall
 * 
 */

namespace Core\System;

use ErrorHandling;
use Log;

 class GWSF extends Base
 {
    protected $_ip = '0.0.0.0';
    protected bool $logging = true;
    protected $blacklisting = true;
    protected string $level = 'low';   // option [low,medium,high]
    const BAD_REQUESTS = [
        'eval(','CONCAT','UNION+SELECT','(null)','base64_','/localhost','/pingserver', '/config.', 
        '/wwwroot','/makefile','crossdomain.','proc/self/environ','etc/passwd','/https/','/http/', 
        '/ftp/','/cgi/','.cgi','.exe','.sql','.ini','.dll','.asp','.jsp','/.bash','/.git','/.svn', 
        '/.tar','  ','<','>','/=','...','+++','://','/&&'
    ];
    const BAD_QUERIES = [
        '?',':','[',']','../','127.0.0.1','loopback','%0A','%0D','%22','%27','%3C','%3E','%00','%2e%2e', 
        'union','input_file','execute','mosconfig','environ','path=.','mod=.' 
    ];
    const BAD_USERAGENT = [
        'binlar','casper','cmswor','diavol','dotbot','finder','flicky','libwww','nutch','planet','purebot',
        'pycurl','skygrid','sucker','turnit','vikspi','zmeu'
    ];

    protected function showError()
    {
        @header('HTTP/1.1 403 Forbidden');
        @header('Status: 403 Forbidden');
        @header('Content-Type: text/html');
        @header('Connection: Close');
        ErrorHandling::getError403();
        @exit;
    }

    protected function blockedAccess()
    {
        $pathDir = GV_STORAGE_DIR . 'gwsf' . DIRECTORY_SEPARATOR;
        $bannedFile = $pathDir . 'banned_ip.gv';
        $whiteFile = $pathDir . 'whitelist_ip.gv';

        if(!is_readable($bannedFile)) {
            make_dir($pathDir, 0755, true);
            @file_put_contents($bannedFile, "", FILE_APPEND | LOCK_EX);
        }
        if(!is_readable($whiteFile)) {
            make_dir($pathDir, 0755, true);
            @file_put_contents($whiteFile, "", FILE_APPEND | LOCK_EX);
        }
        
        $ignored = stripos(file_get_contents($whiteFile), $this->_ip);
        $found = stripos(file_get_contents($bannedFile), $this->_ip);
        if($found !== false && $ignored === false) {
            ErrorHandling::getError1020();
        }
    }

    protected function blockBadQueries() {
        $requestUri = $_SERVER['REQUEST_URI'];
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

        if(str_replace(static::BAD_REQUESTS, '', $requestUri) != $requestUri) {
            if($this->logging) {
                Log::gwsf($requestUri, 'BAD REQUEST');
            }

            if($this->blacklisting) {
                $ip = gv_client_ip();
                $this->addBlacklistIP($ip);
            }

            $this->showError();
        }
        
        if(isset($_SERVER['QUERY_STRING'])) {
            $queryString = $_SERVER['QUERY_STRING'];
            if(str_replace(static::BAD_QUERIES, '', $queryString) != $queryString) {
                if($this->logging) {
                    Log::gwsf($queryString, 'BAD QUERY');
                }

                $this->showError();
            }
        }

        if(str_replace(static::BAD_USERAGENT, '', $userAgent) != $userAgent) {
            if($this->logging) {
                Log::gwsf($userAgent, 'DENY USER AGENT');
            }

            $this->showError();
        }
        
    }

    public function __construct()
    {
        //
        $this->_ip = gv_client_ip();
        $this->blockedAccess();
    }

    public function setLogging(bool $enable)
    {
        $this->logging = $enable;
        return $this;
    }

    public function setLevel(string $level)
    {
        $this->level = $level;
        return $this;
    }

    public function start()
    {
        $this->blockBadQueries();
    }

    public function addBlacklistIP($ip, $permanent=false)
    {
        $pathDir = GV_STORAGE_DIR . 'gwsf' . DIRECTORY_SEPARATOR;
        $pathFile = $pathDir . 'blacklist_ip.gv';
        @make_dir($pathDir, 0755, true);
        $data = date('Y-m-d H:i:s') . '|' . $ip . '|' . (int)$permanent . PHP_EOL;
        @file_put_contents($pathFile, $data, FILE_APPEND | LOCK_EX);
    }
    public function addBanIP($ip)
    {
        $pathDir = GV_STORAGE_DIR . 'gwsf' . DIRECTORY_SEPARATOR;
        $pathFile = $pathDir . 'banned_ip.gv';
        @make_dir($pathDir, 0755, true);
        $data = $ip.PHP_EOL;
        @file_put_contents($pathFile, $data, FILE_APPEND | LOCK_EX);
    }
 }
