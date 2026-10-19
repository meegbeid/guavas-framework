<?php 

class Hash
{
    public static function uuid()
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // versi 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant RFC 4122

        $hex = bin2hex($data);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4));
    }

    public static function random(int $length, $char = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
    {
    	$charLength = strlen($char);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
	        $randomString .= $char[rand(0, $charLength - 1)];
	    }
	    return $randomString;
    }

    private static function passwordAlgorithm(): string|int
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    private static function passwordOptions(): array
    {
        return defined('PASSWORD_ARGON2ID')
            ? ['memory_cost' => 32768, 'time_cost' => 3, 'threads' => 1]
            : ['cost' => 12];
    }

    public static function make(string $password): string
    {
        return password_hash($password, self::passwordAlgorithm(), self::passwordOptions());
    }

    public static function check(string $password, string $correct_hash): bool
    {
        if (password_get_info($correct_hash)['algoName'] !== 'unknown') {
            return password_verify($password, $correct_hash);
        }

        // Password lama: Base64(MD5(password) + SHA1(APP_KEY)).
        $salt = defined('APP_KEY') ? APP_KEY : 'guavas-key';
        $legacyHash = base64_encode(md5($password) . sha1($salt));
        return hash_equals($legacyHash, $correct_hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_get_info($hash)['algoName'] === 'unknown'
            || password_needs_rehash($hash, self::passwordAlgorithm(), self::passwordOptions());
    }

    public static function file(string $file) {
        $salt = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $hash = hash_file('sha1', $file);
        $sha1salt = sha1($salt . $hash);
        return $sha1salt;
    }
}
