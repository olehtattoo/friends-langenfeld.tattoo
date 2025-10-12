<?php
/**
 * waCrashCatcher.php — единый «crash catcher» для PHP
 *
 * Требует заранее объявленных глобалок (из waConfig/waConfig.php):
 *   $WA_MAIN_LOG_DEBUG  — включить (true) / выключить (false) отладочное логирование
 *   $WA_MAIN_LOG_DIR    — каталог для логов
 *   $WA_MAIN_LOG        — файл основного лога (сюда перенаправляется error_log)
 *
 * Поведение:
 *  - Если $WA_MAIN_LOG_DEBUG = false, скрипт ничего не делает.
 *  - При включении: включает E_ALL, пишет предупреждения/ошибки в $WA_MAIN_LOG,
 *    ловит неперехваченные исключения и фатальные ошибки (shutdown) и пишет их
 *    в $WA_MAIN_LOG (и дополнительно в waFatal.log в том же каталоге, если возможно).
 *
 * Подключение (после конфигурации):
 *   require_once __DIR__ . '/waConfig/waConfig.php';
 *   require_once __DIR__ . '/waCrashCatcher.php';
 */

require_once __DIR__ . '/../waConfig/waConfig.php';
require_once __DIR__ . '/waLogDebug.php';

if (defined('WA_CRASH_CATCHER_BOOTSTRAPPED')) {
    return; // защита от повторного подключения
}
define('WA_CRASH_CATCHER_BOOTSTRAPPED', 1);

/* ---------- чтение конфигурации ---------- */
$__waDebug  = !empty($GLOBALS['WA_MAIN_LOG_DEBUG']);                 // true/1/'1' => включено
$__waLogDir = rtrim((string)($GLOBALS['WA_MAIN_LOG_DIR'] ?? ''), "/\\");
$__waMain   = (string)($GLOBALS['WA_MAIN_LOG'] ?? '');

/* ---------- если отладка выключена — выходим ---------- */
if (!$__waDebug) {
    return;
}

/* ---------- ensure: каталог логов ---------- */
if ($__waLogDir !== '') {
    if (!function_exists('__wa_ensure_dir')) {
        function __wa_ensure_dir(string $dir, int $mode = 0775): bool {
            if ($dir === '') return false;
            if (is_dir($dir)) return true;
            if (file_exists($dir) && !is_dir($dir)) return false;
            if (@mkdir($dir, $mode, true)) {
                @chmod($dir, $mode);
                return true;
            }
            return is_dir($dir);
        }
    }
    @__wa_ensure_dir($__waLogDir);
}

/* ---------- базовые настройки логирования PHP ---------- */
@ini_set('display_errors', '1');
@ini_set('log_errors', '1');
@ini_set('html_errors', '0');
@ini_set('default_charset', 'UTF-8');
error_reporting(E_ALL);

// перенаправляем системный error_log в основной лог
if ($__waMain !== '') {
    @ini_set('error_log', $__waMain);
}

/* ---------- удобочитаемое имя уровня ошибки ---------- */
$__wa_level_name = static function (int $severity): string {
    static $map = [
        E_ERROR             => 'E_ERROR',
        E_WARNING           => 'E_WARNING',
        E_PARSE             => 'E_PARSE',
        E_NOTICE            => 'E_NOTICE',
        E_CORE_ERROR        => 'E_CORE_ERROR',
        E_CORE_WARNING      => 'E_CORE_WARNING',
        E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
        E_USER_ERROR        => 'E_USER_ERROR',
        E_USER_WARNING      => 'E_USER_WARNING',
        E_USER_NOTICE       => 'E_USER_NOTICE',
        E_STRICT            => 'E_STRICT',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED        => 'E_DEPRECATED',
        E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
    ];
    return $map[$severity] ?? ('E_' . $severity);
};

/* ---------- обработчик НЕфатальных ошибок ---------- */
set_error_handler(function ($severity, $message, $file, $line) use ($__wa_level_name) {
    // уважать @-оператор
    if (!(error_reporting() & $severity)) {
        return false;
    }
    $payload = [
        'time'    => date('c'),
        'kind'    => 'php_error',
        'level'   => $__wa_level_name((int)$severity),
        'message' => (string)$message,
        'file'    => (string)$file,
        'line'    => (int)$line,
    ];
    @error_log(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    // false => позволить стандартному обработчику тоже отработать (если нужен)
    return false;
});

/* ---------- обработчик неперехваченных исключений ---------- */
set_exception_handler(function (Throwable $ex) {
    $payload = [
        'time'    => date('c'),
        'kind'    => 'uncaught_exception',
        'class'   => get_class($ex),
        'message' => $ex->getMessage(),
        'file'    => $ex->getFile(),
        'line'    => $ex->getLine(),
        'code'    => $ex->getCode(),
        'trace'   => $ex->getTrace(),
    ];
    @error_log(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
});

/* ---------- ловим фаталы на shutdown ---------- */
register_shutdown_function(function () use ($__waLogDir, $__waMain, $__wa_level_name) {
    $e = error_get_last();
    if (!$e) return;

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array($e['type'] ?? 0, $fatalTypes, true)) {
        return;
    }

    $payload = [
        'time'    => date('c'),
        'kind'    => 'fatal_shutdown',
        'level'   => $__wa_level_name((int)$e['type']),
        'message' => (string)($e['message'] ?? ''),
        'file'    => (string)($e['file'] ?? ''),
        'line'    => (int)($e['line'] ?? 0),
    ];
    $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

    // отдельный файл для фаталов в том же каталоге, если доступен
    $fatalFile = ($__waLogDir !== '' ? ($__waLogDir . '/waFatal.log') : '');
    if ($fatalFile !== '') {
        @file_put_contents($fatalFile, $line, FILE_APPEND | LOCK_EX);
    } else {
        // fallback — в системный error_log (который уже направлен на $WA_MAIN_LOG, если задан)
        @error_log(rtrim($line));
    }
});
