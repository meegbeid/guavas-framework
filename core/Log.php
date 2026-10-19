<?php
/**
 * Logging
 *
 * PHP version 8.0
 */
class Log
{

    protected static function buildLog(string $type, mixed $vars)
    {
        $datetime = date('Y-m-d H:i:s');
        if (is_array($vars) || is_object($vars)) {
            $vars = var_export($vars, true);
        } elseif (is_bool($vars)) {
            $vars = $vars ? 'true' : 'false';
        } elseif ($vars === null) {
            $vars = 'null';
        } else {
            $vars = (string) $vars;
        }

        return sprintf(
            "[%s] %s: %s%s",
            $datetime,
            strtoupper($type),
            $vars,
            PHP_EOL
        );
    }

    protected static function writeLog(string $type, mixed $vars, mixed $vars2 = ''): bool
    {
        $logFile = ROOT_DIR . GV_LOG_ERROR;
        make_dir(dirname($logFile), 0755, true);
        $log = self::buildLog($type, $vars);
        if ($vars2 !== '' && $vars2 !== null) {
            $log .= self::buildLog($type, $vars2);
        }

        return file_put_contents($logFile, $log, FILE_APPEND | LOCK_EX) !== false;
    }

    public static function info(mixed $vars, mixed $vars2 = '')
    {
        return self::writeLog('INFO', $vars, $vars2);
    }

    public static function error(mixed $vars, mixed $vars2 = '')
    {
        return self::writeLog('ERROR', $vars, $vars2);
    }

    public static function gwsf(mixed $vars, string $type = 'ERROR')
    {
        $pathDir = ROOT_DIR . 'logs/';
        $pathFile = $pathDir . 'gwsf.log';

        make_dir($pathDir, 0755, true);

        $log = self::buildLog($type, $vars);
        return file_put_contents($pathFile, $log, FILE_APPEND | LOCK_EX) !== false;
    }
}