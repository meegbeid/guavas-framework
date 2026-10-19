<?php
namespace Core\System;

/**
 * A PSR-4 compliant autoloader
 *
 **/
class Autoload 
{
	/**
	 * Registered namespaces/prefixes
	 * @var array
	 */
	protected $namespaces = [];

	/**
	 * Adds the classloader to the SPL autoloader stack
	 * @param  bool $prepend Whether to prepend to the stack
	 * @return bool Returns true on success or false on failure
	 */
	public function register($prepend = false) 
	{
		return spl_autoload_register([$this, 'loadClass'], true, $prepend);
	}

	/**
	 * Removes the classloader from the SPL autoloader stack
	 * @return bool Returns true on success or false on failure
	 */
	public function unregister() 
	{
		return spl_autoload_unregister([$this, 'loadClass']);
	}

	public function addNamespace(string $namespace, $path, $prepend = false) {
		// normalize namespace
		$namespace = trim($namespace, '\\');
		// normalize path
		$path = ROOT_DIR . $path;
		$path = rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
		// add namespace
		if ($prepend) {
			array_unshift($this->namespaces, [$namespace, $path]);
		} else {
			array_push($this->namespaces, [$namespace, $path]);
		}
	}

	/**
	 * Returns the registered namespaces
	 * @return array
	 */
	public function getNamespaces() 
	{
		return $this->namespaces;
	}

	/**
	 * Tries to resolve the given class from the registered namespaces
	 * @param  string $class
	 * @return mixed
	 */
	public function loadClass($class) 
	{
		if(strpos($class,"\\") === false) {
			$path = GV_CORE_DIR;
			if (is_file($classFile = GV_CORE_DIR . $class . '.php')) {
				require $classFile;
				return true;
			}
		}

		// check all registered namespaces
		foreach ($this->namespaces as $namespace) {
			list($prefix, $path) = $namespace;
			// find a matching prefix
			if (strpos($class, $prefix) === 0) {
				$className = ltrim(substr($class, strlen($prefix)), '\\');
				// require the file if it exists
				if (is_file($classFile = $path . str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php')) {
					require $classFile;
					return true;
				}
			}
		}
		// no file was found
		return false;
	}

	public function app($prepend = false) 
	{
		$this->register($prepend);
	}
}
