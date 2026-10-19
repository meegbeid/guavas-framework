<?php

class mod_route
{
    public function __construct(array $args)
    {
        if ($args === [] || in_array('--help', $args, true) || in_array('-h', $args, true) || $args[0] === 'help') {
            echo "Gunakan: php guavas-cmd route Controller@method [--nama-parameter=nilai]\n";
            return;
        }
        $routing = array_shift($args);
        if (!preg_match('/^([A-Za-z][A-Za-z0-9_]*)@([A-Za-z][A-Za-z0-9_]*)$/D', $routing, $match)) {
            fwrite(STDERR, "Format route harus Controller@method.\n");
            exit(1);
        }
        $params = [];
        foreach ($args as $arg) {
            if (!preg_match('/^--([A-Za-z][A-Za-z0-9_-]*)=(.*)$/Ds', $arg, $option)) {
                fwrite(STDERR, "Opsi route tidak valid: $arg\n");
                exit(1);
            }
            $name = str_replace('-', '_', $option[1]);
            if (array_key_exists($name, $params)) {
                fwrite(STDERR, "Parameter route ganda: $name\n");
                exit(1);
            }
            $params[$name] = $option[2];
        }
        $class = '\\App\\Controllers\\' . $match[1];
        if (!class_exists($class) || !method_exists($class, $match[2])) {
            fwrite(STDERR, "Controller atau method tidak ditemukan.\n");
            exit(1);
        }
        $method = new ReflectionMethod($class, $match[2]);
        if (!$method->isPublic() || $method->getDeclaringClass()->getName() !== ltrim($class, '\\') || str_starts_with($method->getName(), '__')) {
            fwrite(STDERR, "Method route tidak dapat dipanggil dari CLI.\n");
            exit(1);
        }
        try {
            $result = $method->isStatic() ? $method->invokeArgs(null, $params) : $method->invokeArgs(new $class, $params);
            if (is_string($result)) echo $result;
        } catch (Throwable $error) {
            fwrite(STDERR, 'Route gagal: ' . $error->getMessage() . PHP_EOL);
            exit(1);
        }
    }
}
