<?php declare(strict_types=1);

/**
 * Единый логгер для всего проекта.
 * Пишет JSONL в $WA_MAIN_LOG при $WA_MAIN_LOG_DEBUG === true.
 * При ошибках записи пишет причину в error_log.
 */

require_once __DIR__ . '/waConfig.php';

/** ================= ВНУТРЕННИЕ УТИЛИТЫ ================= */

if (!function_exists('wa__log_path')) {
    function wa__log_path(): string {
        $path = $GLOBALS['WA_MAIN_LOG'] ?? (__DIR__ . '/waMainLog.log');
        if (!is_string($path) || $path === '') {
            $path = __DIR__ . '/waMainLog.log';
        }
        return $path;
    }
}

if (!function_exists('wa__is_debug')) {
    function wa__is_debug(): bool {
        return (bool)($GLOBALS['WA_MAIN_LOG_DEBUG'] ?? false);
    }
}

if (!function_exists('wa__ensure_logfile')) {
    function wa__ensure_logfile(string $path): bool {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!file_exists($path)) {
            @touch($path);
            @chmod($path, 0664);
        }
        return is_writable($dir) && (!file_exists($path) || is_writable($path));
    }
}

/** ================= ОСНОВНОЙ ЛОГГЕР ================= */

if (!function_exists('wa_log')) {
    function wa_log(string $level, string $message, array $context = []): bool {
        if (!wa__is_debug()) {
            return false; // логирование выключено
        }

        $path = wa__log_path();
        $ok   = wa__ensure_logfile($path);

        $msg = trim($message);
        if (strtoupper($level) === 'ERROR' && strpos($msg, '*ошибка*') !== 0) {
            $msg = '*ошибка* ' . $msg;
        }

        $row = [
            'ts'      => date('c'),
            'level'   => strtoupper($level),
            'message' => $msg,
            'ctx'     => $context,
        ];

        $json = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = json_encode([
                'ts'      => date('c'),
                'level'   => strtoupper($level),
                'message' => $msg,
                'ctx'     => '<<json_encode_failed>>',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($ok) {
            $w = @file_put_contents($path, $json . "\n", FILE_APPEND | LOCK_EX);
            if ($w === false) {
                error_log('[WA_LOG_FAIL] cannot write to ' . $path . ' :: ' . $json);
                return false;
            }
            return true;
        } else {
            error_log('[WA_LOG_FAIL] path not writable ' . $path . ' :: ' . $json);
            return false;
        }
    }
}

/** ================= ШОРТКАТЫ ================= */

if (!function_exists('wa_log_info')) {
    function wa_log_info(string $message, array $context = []): bool {
        return wa_log('INFO', $message, $context);
    }
}
if (!function_exists('wa_log_warn')) {
    function wa_log_warn(string $message, array $context = []): bool {
        return wa_log('WARN', $message, $context);
    }
}
if (!function_exists('wa_log_error')) {
    function wa_log_error(string $message, array $context = []): bool {
        return wa_log('ERROR', $message, $context);
    }
}

/** Вход/выход из функций для тотального трассинга */
if (!function_exists('wa_log_enter')) {
    function wa_log_enter(string $fn, array $args = []): void {
        wa_log_info(">> {$fn}()", ['args' => $args]);
    }
}
if (!function_exists('wa_log_return')) {
    function wa_log_return(string $fn, array $ret = []): void {
        wa_log_info("<< {$fn}()", ['return' => $ret]);
    }
}

/** Безопасный ран с логированием исключений */
if (!function_exists('wa_try')) {
    function wa_try(string $label, callable $fn) {
        wa_log_enter(__FUNCTION__, ['label' => $label]);
        try {
            $r = $fn();
            wa_log_return(__FUNCTION__, ['label' => $label, 'ok' => true]);
            return $r;
        } catch (\Throwable $e) {
            wa_log_error("{$label}: exception", [
                'type'    => get_class($e),
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => substr($e->getTraceAsString(), 0, 4000),
            ]);
            throw $e;
        }
    }
}
