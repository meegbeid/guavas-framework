<?php

use Core\System\GWSF;

/**
 * Get config value by dot notation
 * 
 * @param string $name
 * @param mixed $default
 * @return mixed
 */
function config(string $name, mixed $default = null): mixed 
{
	static $cache = [];
	static $loadedFiles = [];

	// Return dari cache key
    if (array_key_exists($name, $cache)) {
        return $cache[$name];
    }

	// Validasi format
    if (!str_contains($name, '.')) {
        return $cache[$name] = $default;
    }

	[$file, $keys] = explode('.', $name, 2);

	if (!isset($loadedFiles[$file])) {
		$filePath = GV_APP_DIR . "config/{$file}.php";

        if (!is_file($filePath)) {
            return $cache[$name] = $default;
        }

        $loadedFiles[$file] = require $filePath;
	}
	$config = $loadedFiles[$file];

	// Traverse nested key
    foreach (explode('.', $keys) as $key) {
        if (!is_array($config) || !array_key_exists($key, $config)) {
            return $cache[$name] = $default;
        }
        $config = $config[$key];
    }

    return $cache[$name] = $config;
}
function gv_encode(string $string): string
{
	$salt_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$salt = substr(str_shuffle($salt_chars), 0, 4);

	$encoded = base64_encode($string);
	return substr_replace($encoded, $salt, 1, 0);
}

function gv_decode(string $string): string
{
	$clean = substr_replace($string, '', 1, 4);
	return base64_decode($clean);
}

function gv_encrypt(string $string, $key = 'gv')
{
	$salt = 'guavas-hash-key';
    $key = substr(hash('sha256', $salt . $key . $salt), 0, 32);
    $encryption_key = base64_decode($key);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($string, 'aes-256-cbc', $encryption_key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

function gv_decrypt(string $string, $key = 'gv')
{
	$salt = 'guavas-hash-key';
    $key = substr(hash('sha256', $salt . $key . $salt), 0, 32);
    $encryption_key = base64_decode($key);
    @list($encrypted_data, $iv) = explode('::', base64_decode($string), 2);
    return @openssl_decrypt($encrypted_data, 'aes-256-cbc', $encryption_key, 0, trim($iv));
}

function gv_debug($limit = 5, $withArgs = false)
{
	$options = $withArgs ? 0 : DEBUG_BACKTRACE_IGNORE_ARGS;
    $backtrace = debug_backtrace($options, $limit);
	
	predump($backtrace);
}

function cleanHTMLTags(string $string, $flags = [])
{
    //$noQuotes = in_array(FILTER_FLAG_NO_ENCODE_QUOTES, $flags);
    //$options = ($noQuotes ? ENT_NOQUOTES : ENT_QUOTES) | ENT_SUBSTITUTE;

    // Strip the tags
    $string = strip_tags($string);

    // Run the replacement for FILTER_SANITIZE_STRING
    //$string = htmlspecialchars($string, $options);

    // Decode all entities
    return $string;
}

function cleanXSS(string $data) 
{
	$originalData = $data;
	// Fix &entity\n;
	$data = str_replace(array('&amp;','&lt;','&gt;'), array('&amp;amp;','&amp;lt;','&amp;gt;'), $data);
	$data = preg_replace('/(&#*\w+)[\x00-\x20]+;/u', '$1;', $data);
	$data = preg_replace('/(&#x*[0-9A-F]+);*/iu', '$1;', $data);
	$data = html_entity_decode($data, ENT_COMPAT, 'UTF-8');

	// Remove any attribute starting with "on" or xmlns
	$data = preg_replace('#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+>#iu', '$1>', $data);

	// Remove javascript: and vbscript: protocols
	$data = preg_replace('#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2nojavascript...', $data);
	$data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2novbscript...', $data);
	$data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#u', '$1=$2nomozbinding...', $data);

	// Only works in IE: <span style="width: expression(alert('Ping!'));"></span>
	$data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?expression[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
	$data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?behaviour[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
	$data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:*[^>]*+>#iu', '$1>', $data);

	// Remove namespaced elements (we do not need them)
	$data = preg_replace('#</*\w+:\w[^>]*+>#i', '', $data);

	do
	{
		// Remove really unwanted tags
		$old_data = $data;
		$data = preg_replace('#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i', '', $data);
	}
	while ($old_data !== $data);

	if($originalData !== $data) {
		$ip = gv_client_ip();
		$gwsf = new GWSF;
		$gwsf->addBlacklistIP($ip);
	}
	
	// return result
	return $data;
}

function gv_filter_names(string $name) 
{
	$name = cleanXSS($name);
	$name = strip_tags($name); 
	$name = preg_replace( '/[^a-z \']/i', '', $name);
	return $name;
}

function gv_post(string $key, $default=null, $xss=false)
{
	if(isset(GV_INPUT_POST[$key])) {
		$input = GV_INPUT_POST[$key];
		if($xss && is_string($input)) {
			$input = cleanXSS($input);
		}
		return $input;
	}
	return $default;
}

function gv_get(string $key, $default=null, $xss=true) 
{
	if(isset(GV_INPUT_GET[$key])) {
		$input = GV_INPUT_GET[$key];
		if($xss && is_string($input)) {
			$input = cleanXSS($input);
		}
		return $input;
	}
	return $default;
}

function gv_request(?string $key = null, mixed $default = null, bool $xss = false): mixed
{
    $input = GV_INPUT_POST + GV_INPUT_GET;
    if ($key === null) {
        return $input;
    }

    $value = $input[$key] ?? $default;
    return $xss && is_string($value) ? cleanXSS($value) : $value;
}

function gv_files(?string $key = null, mixed $default = null): mixed
{
    return $key === null ? $_FILES : ($_FILES[$key] ?? $default);
}

function gv_header(string $key)
{
	return GV_INPUT_HEADERS[$key] ?? null;
}

/**
 * This function displays structured information about one or more expressions that includes its type and value. 
 * Arrays and objects are explored recursively with values indented to show structure. 
 * 
 * @param 	mixed	$vars 	The expression to dump. 
 * @param 	boolean	$asJSON	Set true if return with content type json on browser
 * 
 * @return 	null	No value returned
 */
function predump($vars, $asJSON = true) 
{
	if($asJSON) {
		$json = json_encode($vars);
		header('Content-Type: application/json');
		echo $json;
	} else {
		echo "<pre>";
		var_dump($vars);
		echo "</pre>";
	}
	exit();
}

function storage($path = '', $withBuildDirectory = false, $permission = 0755)
{
	$path = ROOT_DIR . 'storage/' . slashtrim($path);
	if($withBuildDirectory) {
		@make_dir($path, $permission, true);
	}
	return $path;
}

function server($name = null)
{
	if ($name === null || $name === '') {
        return $_SERVER;
    }
	return $_SERVER[$name] ?? null;
}

function base_url($uri = '')
{
	return BASE_URL . '/' . slashtrim($uri);
}

function make_dir(string $path, $permission = 0777, $recursive = false) 
{
	// create directory if not exist
    return is_dir($path) || mkdir($path, $permission, $recursive);
}
function make_file(string $path, $permission = 0644) 
{
	return @file_put_contents($path,"");
}
function remove_dir(string $dir) {
    if (!is_dir($dir)) return false;

    $it = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);

    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
    return rmdir($dir);
}

function slashtrim(string $string)
{
	return trim($string, '\\/');
}
function quotetrim(string $string)
{
	$string = trim($string, '"');
	$string = trim($string, "'");
	return $string;
}
function str2bool(string $value)
{
	return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}
function int2az(int $num) {
    $result = '';
    while ($num > 0) {
        $remainder = ($num - 1) % 26;
        $result = chr(65 + $remainder) . $result;
        $num = intval(($num - 1) / 26);
    }
    return $result;
}
function slugify(string $string)
{
	$string = preg_replace('/[^A-Za-z0-9-]+/', '-', $string);
	$string = trim($string);
	$string = strtolower($string);
	$string = trim($string,'-');
	return $string;
}
function url($uri = '')
{
	$target = base_url() . ltrim($uri, '/');
	if (headers_sent()) {
		die("Redirect failed. Please click on this link: <a href='".$target."'>".$target."</a>");
	} else {
		exit(header("Location: ".$target));
	}
}
function asset(string $path): string 
{
	$base = rtrim(base_url('assets'), '/');
	if ($path === '') {
        return $base;
    }
	return $base . '/' . ltrim($path, '/');
}
function format_size_units(float $bytes) 
{
	if ($bytes <= 0) {
        return '0 B';
    }

	$sizes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

	$i = floor(log($bytes, 1024));
	$i = min($i, count($sizes) - 1);

	return round($bytes / pow(1024, $i), 2) . ' ' . $sizes[$i];
}
function selectopt(array $options, $defaultValue = null) 
{
	$html = [];
	foreach($options as $opt) {
		$selected = $opt['name'] == $defaultValue ? ' selected' : ''; 
		$html[] = '<option value="'.$opt['name'].'" '.$selected.'>'.$opt['value'].'</option>';
	}
	return implode($html);
}
function gv_between(mixed $num, mixed $first, mixed $last) 
{
	return $num >= min($first, $last) && $num <= max($first, $last);
}

function gv_client_ip(bool $publicOnly = false): string
{
	$keys = [
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_X_REAL_IP',        // Nginx
        'HTTP_X_FORWARDED_FOR',  // Reverse Proxy / Load Balancer
        'REMOTE_ADDR'
    ];

	foreach ($keys as $key) {
        if (empty($_SERVER[$key])) {
            continue;
        }

		$ips = explode(',', $_SERVER[$key]);

		foreach ($ips as $ip) {
            $ip = trim($ip);

            $flags = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;

            if ($publicOnly) {
                $flags |= FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
            }

            if (filter_var($ip, FILTER_VALIDATE_IP, $flags)) {
                return $ip;
            }
        }
	}

    return 'UNKNOWN';
}
function gv_remote_addr_ip(): ?string {return $_SERVER['REMOTE_ADDR'] ?? null;}
function gv_x_forwarded_for(): ?string {return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;}
function gv_cf_connecting_ip(): ?string {return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null;}
function gv_x_real_ip(): ?string {return $_SERVER['HTTP_X_REAL_IP'] ?? null;}

function get_user_agent() 
{
	if(isset($_SERVER['HTTP_USER_AGENT']))
	{
		return $_SERVER['HTTP_USER_AGENT'];
	}
	return 'Unknown';
}

/**
 * encode image
 */
function image_base64(string $fileUrl) 
{
	$type = pathinfo($fileUrl, PATHINFO_EXTENSION);
	$contentFile = file_get_contents($fileUrl);
  	$image = 'data:image/'.$type.';base64,'.base64_encode($contentFile);
  	return $image;
}

function csrf_create(int $expiry = CSRF_EXPIRY, $force = false) 
{
	$csrf = Session::get('csrf_session');
	if($csrf && ($csrf['timestamp'] > time()) && !$force) {
		return $csrf['token'];
	} else {
		$token = gv_encrypt(time() . uniqid(mt_rand(0,990), APP_KEY), APP_KEY);
		$csrf_session['token'] = $token;
		$csrf_session['timestamp'] = time() + $expiry;
		Session::set('csrf_session', $csrf_session);

		return $token;
	}
	return false;
}

function csrf_validate(string $token) 
{
	$result = false;
	$csrf_session = Session::get('csrf_session');
	if(isset($csrf_session['token'])) {
		if($csrf_session['timestamp'] > time()) {
			return $csrf_session['token'] == $token;
		}
		csrf_create(CSRF_EXPIRY, true);
	}
	return $result;
}

function csrf_form() 
{
	return  '<input type="hidden" name="_token" value="'.csrf_create().'">';
}

function error_handler(int $errno, string $errmsg, string $filename, int $linenum)
{
    // error was suppressed with the @-operator
    if (!(error_reporting() & $errno)) {
        return false;
    }

    throw new \ErrorException(
        $errmsg,
        0,
        $errno,
        $filename,
        $linenum
    );

}

if (!function_exists('getallheaders')) 
{
	function getallheaders() {
		$headers = [];
		foreach ($_SERVER as $name => $value) {
			if (substr($name, 0, 5) == 'HTTP_') {
				$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
			}
		}
		return $headers;
	}
}
