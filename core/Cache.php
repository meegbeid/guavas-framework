<?php
/**
 * Cache Storage
 *
 * PHP version 7.2
 */
class Cache
{

    /**
     * Path to the cache directory
     * @var string
     */
    public static $cacheDir = ROOT_DIR . GV_CACHE_DIR;

    /**
     * Returns data associated with $key
     * @param string $key
     * @param bool $meta if true, array will be returned containing metadata alongside data itself
     * @return mixed|null returns data if $key is valid and not expired, NULL otherwise
     * @throws \Exception if the file cannot be saved
     */
	public static function get($key, $meta = false) 
	{
        $cacheFiles = glob(static::$cacheDir . GV_CACHE_PREFIX . $key . '.*');
        foreach($cacheFiles as $file) {
            if(is_file($file)) {
            	$ext = (int) pathinfo($file, PATHINFO_EXTENSION);
            	if($ext > time()) {
            		$cacheContent = @file_get_contents($file);
            		if($data = @unserialize($cacheContent)) {
                        return $meta ? $data : $data['data'];
                    }
            	}
                @unlink($file); //delete file
            }
        }
        return false;
	}

	/**
     * Stores $data under $key for $expiration seconds
     * If $key is already used, then current data will be overwritten
     * @param string $key key associated with the current data
     * @param mixed $data data to store
     * @param $expiration int number of seconds before the $key expires
     * @param $permanent bool if true, this item will not be automatically cleared after expiring
     * @return $hash-sum
     * @throws \Exception if the file cannot be saved
     */
	public static function save($key, $data, $expiration = 86400, $permanent = false)
	{
		if(!is_string($key)) {
            throw new \InvalidArgumentException('$key must be a string, got type "' . get_class() . '" instead');
        }

        $expiry = time() + (int)$expiration;
        $storeData = [
        	'expire' => date('Y-m-d H:i:s', $expiry),
        	'permanent' => $permanent,
        	'data-type' => gettype($data),
        	'data' => $data
        ];

        if (!file_exists(static::$cacheDir))
            @mkdir(static::$cacheDir);

        self::clear($key);
       	$storeData['hash-sum'] = md5(serialize($storeData));
       	$cacheFilePath = static::$cacheDir . GV_CACHE_PREFIX . $key . '.' . $expiry;
       	$cacheData = serialize($storeData);
       	$success = file_put_contents($cacheFilePath, $cacheData) !== false;

        if (!$success)
            throw new \Exception("Cannot save cache");

        return $storeData['hash-sum'];
	}

	public static function clear($name = '*')
	{
		$counter = 0;
        $cacheFiles = glob(static::$cacheDir . GV_CACHE_PREFIX . $name . '.*');
        foreach($cacheFiles as $file){
            if(is_file($file)) {
                if (@unlink($file)) $counter++;
            }
        }
        return $counter;
	}

    public static function clearExpiredOnly()
    {
        $counter = 0;
        $cacheFiles = glob(static::$cacheDir . GV_CACHE_PREFIX . '*.*');
        foreach($cacheFiles as $file){
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            if(is_file($file) && ($ext < time())) {
                if (@unlink($file)) $counter++;
            }
        }
        return $counter; 
    }

    public static function reset()
    {
        self::$cacheDir = ROOT_DIR . GV_CACHE_DIR;
        return true;
    }
    public static function clearViews()
    {
        $counter = 0;
        $dir = static::$cacheDir . 'views';

        if (!is_dir($dir)) {
            return 0;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            if ($fileinfo->isDir()) {
                @rmdir($fileinfo->getRealPath());
            } else {
                @unlink($fileinfo->getRealPath());
            }
            $counter++;
        }

        return $counter; 
    }
		
}
