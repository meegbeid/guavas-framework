<?php

/**
 * Router Class for Guavas-Framework
 * @author      Roywae
 * @category    core
 * @package     Router
 * @version     3.0.0
 */
class Router
{
    protected array $routes = [
        'GET'     => [],
        'POST'    => [],
        'PUT'     => [],
        'OPTIONS' => [],
        'PATCH'   => [],
        'DELETE'  => [],
        'HEAD'    => []
    ];

    protected const PATTERNS = [
        ':any'    => '.*',
        ':id'     => '[0-9]+',
        ':prefix' => '[a-z0-9\-_]+',
        ':slug'   => '[a-zA-Z0-9\-]+',
        ':code'   => '[a-zA-Z0-9]+',
        ':name'   => '[a-zA-Z]+',
    ];

    protected const REGVAL = '/{(:.+?)}/';

    protected array $middlewares = [];
    protected array $params = [];
    protected bool $passthru = false;
    protected mixed $fallback = null;
    protected array $groups = [];

    public function __construct() 
    {
    }

    public function get(string $path, mixed $handler): self { return $this->addRoute('GET', $path, $handler); }
    public function post(string $path, mixed $handler): self { return $this->addRoute('POST', $path, $handler); }
    public function put(string $path, mixed $handler): self { return $this->addRoute('PUT', $path, $handler); }
    public function patch(string $path, mixed $handler): self { return $this->addRoute('PATCH', $path, $handler); }
    public function delete(string $path, mixed $handler): self { return $this->addRoute('DELETE', $path, $handler); }
    public function options(string $path, mixed $handler): self { return $this->addRoute('OPTIONS', $path, $handler); }
    public function head(string $path, mixed $handler): self { return $this->addRoute('HEAD', $path, $handler); }
    public function any(string $path, mixed $handler): self
    {
        foreach (array_keys($this->routes) as $method) {
            $this->addRoute($method, $path, $handler);
        }
        return $this;
    }
    public function middleware(array $middlewares): self
    {
        $this->middlewares = $middlewares;
        return $this;
    }

    public function headers($headers = []) {
        foreach($headers as $header) {
            header($header);
        }
        return $this;
    }
    protected function addRoute(string $method, string $path, mixed $handler): self
    {
        $prefix = implode('/', $this->groups);
        $fullPath = slashtrim(BASE_DIR . '/' . slashtrim($prefix . '/' . $path));

        $this->routes[$method][] = [
            'route'       => $fullPath,
            'handler'     => $handler,
            'middlewares' => $this->middlewares,
        ];

        $this->middlewares = []; // reset untuk route berikutnya
        return $this;
    }

    public function group(string $prefix, callable $callback): self
    {
        $prefix = trim($prefix, '/');
        if ($prefix !== '') {
            $this->groups[] = $prefix;
        }

        try {
            $callback($this);
        } finally {
            if ($prefix !== '') {
                array_pop($this->groups);
            }
        }

        return $this;
    }

    protected function normalizePattern(string $route): string
    {
        $route = preg_replace('~(\\\\)+~', '', $route);
        $route = preg_replace('~(\/)+~', '\/', $route);
        $route = trim($route, '\/');

        $route = preg_replace_callback('/\{\:\((.*?)\)\}/', function ($matches) {
            return '(' . $matches[1] . ')';
        }, $route);   

        return preg_replace_callback(self::REGVAL, function ($matches) {
            return '(' . (self::PATTERNS[$matches[1]] ?? '[^/]+') . ')';
        }, $route);
    }
    protected function stringHandler(string $handler, array $args = [])
    {
        [$controller, $method] = array_pad(explode('@', $handler), 2, null);

        if (!class_exists($controller)) {
            throw new Exception("Controller $controller not found");
        }

        $ctrl = new $controller;
        foreach ($this->params as $key => $par) {
            $ctrl->$key = $par;
        }

        if (!method_exists($ctrl, $method)) {
            throw new Exception("Method $method not found in $controller");
        }

        return call_user_func_array([$ctrl, $method], $args);
    }
    protected function callableHandler(callable $handler, array $args = [])
    {
        return call_user_func_array($handler, $args);
    }
    protected function runRouteCommand(mixed $command): void
    {
        if (is_string($command) || is_numeric($command)) {
            echo $command;
        } elseif (is_array($command) || is_object($command)) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode($command);
        } elseif (is_null($command)) {
            // do nothing
        } else {
            throw new Exception("Unknown output format");
        }
    }
    protected function runMiddleware(array $middlewares): void
    {
        foreach ($middlewares as $middleware) {
            $this->passthru = true;
            $controller = 'App\\Middlewares\\' . $middleware;
            if (!class_exists($controller)) {
                throw new Exception("Middleware $middleware not found");
            }
            $instance = new $controller;
            $response = $instance->handle($this);
            if (!$this->passthru) {
                $this->runRouteCommand($response);
                exit;
            }
        }
    }
    protected function match(array $resource, string $curRoute): array
    {
        $pattern = $this->normalizePattern($resource['route']);
        $matched = preg_match_all("/^$pattern$/i", $curRoute, $matches);
        array_shift($matches);
        $args = array_column($matches, 0);
        return [$matched, $args];
    }
    protected function middlewarePassthru(mixed $middleware) 
    {
        $this->passthru = true;
        $controller = 'App\Middlewares\\'.$middleware;
        $class = new $controller;
        $nexthop = $class->handle($this);
        if(!$this->passthru) {
            $this->runRouteCommand($nexthop);
            @exit;
        }
    }
    public function fallback(callable $handler): self
    {
        $this->fallback = $handler;
        return $this;
    }
    public function dispatch() 
    {
        // find match routing
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $skipCsrf = in_array(CURRENT_ROUTE, CSRF_ROUTE_EXCEPT, true);

        // Optional CSRF check for all methods except GET/HEAD/OPTIONS
        if(!$skipCsrf 
            && str2bool(ENABLE_CSRF_VERIFY) 
            && !in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)
        ) {
            $token = gv_post('_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
            if (empty($token) || !is_string($token) || !csrf_validate($token)) {
                http_response_code(403);
                ErrorHandling::getError403('CSRF token mismatch');
                exit;
            }
        }

        foreach ($this->routes[$method] as $resource) {
            [$matched, $args] = $this->match($resource, CURRENT_ROUTE);
            if (!$matched) {
                continue;
            }
            // middleware
            if (!empty($resource['middlewares'])) {
                $this->runMiddleware($resource['middlewares']);
            }
            $handler = $resource['handler'];
            // execute handler
            if (is_string($handler)) {
                $result = $this->stringHandler($handler, $args);
            } elseif (is_callable($handler)) {
                $result = $this->callableHandler($handler, $args);
            } else {
                throw new Exception('Invalid route handler type');
            }

            // route command
            $this->runRouteCommand($result);

            return true;
        }

        if ($this->fallback) {
            call_user_func($this->fallback);
            return true;
        }

        // display not found page
        http_response_code(404);
        ErrorHandling::getError404();
        return false;
    }
}
