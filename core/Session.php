<?php
/**
 * URL Class
 * @author      Roywae
 * @category    framework
 * @package     URL
 * @version     1.0.0
 */

class Session {

	/**
     * Get a session Id.
     * @return string|false session_id()
     */
	public static function getId() 
    {
        return session_id();
    }

    /**
     * Get a session Name.
     * @return string|false session_name()
     */
    public static function getName() 
    {
        return session_name();
    }

    /**
     * Set a session variable.
     * @param string $key
     * @param mixed  $value
     * @return $this
     */
    public static function set($key, $value) 
    {
        $_SESSION[GV_SESSION_NAME.$key] = $value;
        return false;
    }

    /**
     * Get a session variable.
     * @param string $key
     * @return mixed
     */
    public static function get($key)
    {
        if(array_key_exists(GV_SESSION_NAME.$key, $_SESSION)){
            return $_SESSION[GV_SESSION_NAME.$key]; 
        }
        return false;
    }

    /**
     * Merge values recursively.
     * @param string $key
     * @param mixed  $value
     * @return $this
     */
    public static function merge($key, $value) 
    {
        if (is_array($value) && is_array($old = self::get($key))) {
            $value = array_merge_recursive($old, $value);
        }
        return self::set($key, $value);
    }

    /**
     * Delete a session variable.
     * @param string $key
     * @return $this
     */
    public static function delete(string $key)
    {
        if (array_key_exists(GV_SESSION_NAME.$key, $_SESSION)) {
            unset($_SESSION[GV_SESSION_NAME.$key]);
        }
        return false;
    }

    /**
     * Clear all session variables.
     * @return $this
     */
    public static function clear() 
    {
        $_SESSION = [];
        return $_SESSION;
    }

    /**
     * Count elements of an object.
     * @return int
     */
    public static function count() 
    {
        return count($_SESSION);
    }
    public static function regenerate()
    {
        session_regenerate_id(TRUE);
        return session_id();
    }
    public static function destroy()
    {
        session_unset();
        session_destroy();
        session_write_close(); 
    }

    /**
     * Check if a session variable is set.
     * @param string $key
     * @return bool
     */
    public static function exists($key) {
        return array_key_exists(GV_SESSION_NAME.$key, $_SESSION);
    }

}