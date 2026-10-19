<?php
/**
 * Guavas Template Engine (GuavasTE) - Optimized
 * @author  Roywae
 * @version 3.0.0
 */
class GuavasTE
{
    protected string $viewsDir;
    protected string $cacheDir;
    protected array $directives = [];

    public function __construct(?array $options = [])
    {
        $this->viewsDir = rtrim($options['views'] ?? GV_APP_DIR . '/views', "\/\\");
        $this->cacheDir  = rtrim($options['cache'] ?? GV_STORAGE_DIR . '/cache/views', "\/\\");

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }

        // register basic directives
        $this->registerDefaultDirectives();
    }

    protected function registerDefaultDirectives()
    {
        // control structure tags
        $this->registerDirective('/\{@if\s*\((.*?)\)\}/', '<?php if ($1): ?>');
        $this->registerDirective('/\{@elseif\s*\((.*?)\)\}/', '<?php elseif ($1): ?>');
        $this->registerDirective('/\{@else\}/', '<?php else: ?>');
        $this->registerDirective('/\{@endif\}/', '<?php endif; ?>');

        $this->registerDirective('/\{@each\s*\((.*?)\)\}/', '<?php foreach ($1): ?>');
        $this->registerDirective('/\{@endeach\}/', '<?php endforeach; ?>');
        $this->registerDirective('/\{@for\s*\((.*?)\)\}/', '<?php for ($1): ?>');
        $this->registerDirective('/\{@endfor\}/', '<?php endfor; ?>');
        $this->registerDirective('/\{@while\s*\((.*?)\)\}/', '<?php while ($1): ?>');
        $this->registerDirective('/\{@endwhile\}\b/', '<?php endwhile; ?>');

        $this->registerDirective('/\{@asset\s*\((.*?)\)\}/', '<?php echo asset($1); ?>');
        $this->registerDirective('/\{@url\s*\((.*?)\)\}/', '<?php echo base_url($1); ?>');
        $this->registerDirective('/\{@(uniqid|csrf_create|csrf_form)\s*\(\)\}/', '<?php echo $1(); ?>');
        $this->registerDirective('/\{(BASE_URL)\}/', '<?php echo $1; ?>');
        $this->registerDirective('/\{@selectopt\s*\((.*?)\)\}/', '<?php echo selectopt($1); ?>');
    }

    /**
     * Register a custom regex-based directive replacement
     * pattern: regex (delimitered), 
     * replacement: php replacement using $1, $2
     */
    protected function registerDirective(string $pattern, string $replacement): void
    {
        $this->directives[$pattern] = $replacement;
    }

    /**
     * Get view path
     * @param string $name
     * @return string
     */
    protected function viewPath(string $name): string
    {
        $path = $this->viewsDir . '/' . ltrim($name, '/\\');
        if (!preg_match('/\.php$|\.gv$|\.tpl$\.html$/', $path, $matches)) {
            // default extension
            $path = str_replace('.', '/', $path) . '.php';
        }
        return $path;
    }

    /**
     * Build cache path with 3 level deep
     */
    protected function writeCachePath(string $name): string
    {
        $cacheDir = $this->cacheDir . '/' . implode('/', [
            $name[0],
            $name[1],
            $name[2]
        ]);
        make_dir($cacheDir, 0755, true);
        return $cacheDir;
    }

    /**
     * Write Cache Path
     */
    protected function cachePath(string $name): string
    {
        $hash = md5($name);
        return $this->writeCachePath($hash) . '/gv_' . $hash . '.php';
    }
    /**
     * Get value of variable - if using separator dot (.)
     * @param string $path
     */
    protected function getValue(string $path) 
    {
        if ($path === '') {
            return '';
        }
        $keys = explode('.', $path);
        $result = array_shift($keys);
        foreach ($keys as $key) {
            $result .= "['{$key}']";
        }
        return $result;
    }
    /**
     * Convert template source to PHP code (returns full PHP file contents)
     */
    public function compileString(string $source, null|string $originPath = null, null|string $logicalName = null): string
    {
        // normalize line endings
        $source = str_replace(["\r\n","\r"], "\n", $source);

        // handle @layout: if present, we will compile the child to register sections and then include parent
        $layout = null;
        if (preg_match('/@layout\s*\(\s*[\'\"]([^\'\"]+)[\'\"]\s*\)/', $source, $m)) {
            $layout = $m[1];
            // remove layout token from child template source (we'll include parent at runtime)
            $source = preg_replace('/@layout\s*\(.*?\)\s*/', '', $source, 1);
        }

        // first: capture @section blocks
        $sections = [];
        if (preg_match_all('/@section\s*\(\s*[\'\"]([^\'\"]+)[\'\"]\s*\)(.*?)@endsection/s', $source, $sm, PREG_SET_ORDER)) {
            foreach ($sm as $sec) {
                $sections[$sec[1]] = $sec[2];
            }
            // remove the section blocks from source
            $source = preg_replace('/@section\s*\(\s*[\'\"]([^\'\"]+)[\'\"]\s*\)(.*?)@endsection/s', '', $source);
        }

        // replace echo tags
        // escaped: {{ ... }}
        $source = preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/s', function ($m) {
            return '<?php echo ' . $m[1] . '; ?>';
        }, $source);

        // handle: @php ... @endphp
        $source = preg_replace_callback('/\{?\@php\}?\s*(.+?)\s*\{?@endphp\}?/s', function ($m) {
            return '<?php ' . $m[1] . ' ?>';
        }, $source);

        // handle: $var
        $source = preg_replace_callback('/\{(@)?\$([\w.]+)\}/s', function ($m) {
            return '<?php echo '.$m[1].'$' . $this->getValue($m[2]) . ' ?>';
        }, $source);

        // process includes: @include('name')
        $source = preg_replace_callback('/@include\s*\(\s*[\'\"]([^\'\"]+)[\'\"]\s*\)/', function ($m) use ($logicalName) {
            $incName = $m[1];
            $cacheInc = $this->cachePath($incName);
            // ensure included template is compiled
            if (!file_exists($cacheInc) || filemtime($cacheInc) < filemtime($this->viewPath($incName))) {
                $this->compile($incName);
            }
            return '<?php include \'' . addslashes($cacheInc) . '\'; ?>';
        }, $source);

        // obtain: @obtain('name') => echo section content if defined
        $source = preg_replace('/@obtain\s*\(\s*[\'\"]([^\'\"]+)[\'\"]\s*\)/', '<?php echo $__sections[\'$1\'] ?? ""; ?>', $source);

        // apply regex directives (control structures)
        foreach ($this->directives as $pat => $rep) {
            $source = preg_replace($pat, $rep, $source);
        }

        $php = "<?php\n// Compiled by GuavasTE. Origin: " . ($originPath ?? 'string') . "\n?>\n";
        
        // if extends: compiled child must register sections and include parent cache
        if ($layout !== null) { 
            // compile parent so its cache path is available
            $parentCache = $this->cachePath($layout);
            if (!file_exists($parentCache) || filemtime($parentCache) < filemtime($this->viewPath($layout))) {
                $this->compile($layout);
            }

            // prepare PHP that registers sections into $__sections and includes parent cache
            $php .= "<?php\n// child template registering sections\n\$__sections = \$__sections ?? [];\n";
            foreach ($sections as $name => $body) {
                // compile the section body too (recursively) to PHP code fragment
                $compiledBody = $this->compileString($body, null, $logicalName . '::section::' . $name);
                // We want the compiled body to be evaluated when parent yields; easiest approach: write it to a temporary file and include it when assigning $__sections[name]
                $md5name = md5($logicalName . '::' . $name);
                $secCache = $this->writeCachePath($md5name) . '/section_' . $md5name . '.php';
                file_put_contents($secCache, $compiledBody);
                $php .= "ob_start(); include '" . addslashes($secCache) . "'; \$__sections['" . addslashes($name) . "'] = ob_get_clean();\n";
            }

            $php .= "?>\n<?php include '" . addslashes($parentCache) . "'; ?>\n";

            // Note: any remaining source outside sections will be appended after parent include (rare)
            if (trim($source) !== '') {
                $php .= "<!-- child leftover output -->\n" . $source . "\n";
            }

            return $php;
        }

        if (!empty($sections)) {
            $php .= "<?php\n\$__sections = \$__sections ?? [];\n";
            foreach ($sections as $name => $body) {
                $compiledBody = $this->compileString($body, null, $logicalName . '::section::' . $name);
                $md5name = md5($logicalName . '::' . $name);
                $secCache = $this->writeCachePath($md5name) . '/section_' . $md5name . '.php';
                file_put_contents($secCache, $compiledBody);
                $php .= "ob_start(); include '" . addslashes($secCache) . "'; \$__sections['" . addslashes($name) . "'] = ob_get_clean();\n";
            }
            $php .= "?>\n";
        }

        $php .= $source . "\n";

        return $php;
    }
    protected function compile(string $name)
    {
        $view = $this->viewPath($name);
        if (!file_exists($view)) {
            throw new RuntimeException("Template not found: $view");
        }

        $cache = $this->cachePath($name);

        if (!file_exists($cache) || filemtime($cache) < filemtime($view)) {
            $content = file_get_contents($view);
            $compiled = $this->compileString($content, $view, $name);
            file_put_contents($cache, $compiled);
        }

        return $cache;
    }
    /**
     * Render a template with data
     */
    public function render(string $name, array $data = []): string
    {
        $compiled = $this->compile($name);

        // extract data to local scope
        $data['CSRFToken'] = csrf_create();
        extract($data, EXTR_SKIP);

        // start output buffer and include compiled template
        ob_start();
        try {
            include $compiled;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return ob_get_clean();
    }
}