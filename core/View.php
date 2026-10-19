<?php
/**
 * ErrorHandling Class for Guavas-Framework
 * @author      Roywae
 * @category    core
 * @package     View
 * @version     3.0.0
 */
class View
{

	/**
	 * Fullpath view file location
	 *
	 * @var	string
	 */
	const PATH_VIEW = GV_APP_DIR . 'views/';
    const PATH_CACHE = GV_STORAGE_DIR . 'cache/views/';

    protected static ?GuavasTE $engine = null;
	protected static bool $disableCache = false;

	public function __construct()
	{
	}

	public static function disableCache($status = true)
    {
        static::$disableCache = (bool) $status;
    }

	// public static function __callstatic($name, $args) 
	// {
	// 	$ctrl = new static;
    //     if(method_exists($ctrl, $name)) {
    //         $result = call_user_func_array([$ctrl, $name], $args);
    //         return $result;
    //     } else {
    //         throw new \Exception("Method $name not found in Cache Object " . get_class($ctrl));
    //     }
	// }

	/**
     * Render a view file
     *
     * @param string $viewName  The view file
     * @param mixed $args  Associative array of data to display in the view (optional)
     *
     * @return mixed
     */
	public static function render(string $viewName, $args = [], $gve_enabled = true): mixed
    {
        if (!self::$engine) {
            self::$engine = new \GuavasTE([
                'views' => static::PATH_VIEW,
                'cache' => static::PATH_CACHE
            ]);
        }
        $viewName = str_replace('.', '/', $viewName) . '.php';
        $viewFile = static::PATH_VIEW . slashtrim($viewName);

        if (!is_file($viewFile)) {
            return "{Error: File {$viewName} not found.}";
        }

        if ($gve_enabled) {
            return self::$engine->render($viewName, $args);
        }

        try {
			// Prevent overriding existing variables
			extract($args, EXTR_SKIP);

			// Capture output
			ob_start();
			require $viewFile;
			return ob_get_clean();
		} catch (\Throwable $e) {
			ob_end_clean();
			return "{Render Error: " . $e->getMessage() . "}";
		}
    }

}
