<?php declare(strict_types=1);


// === CRASH CATCHER ===
/*ini_set('display_errors','1');
ini_set('log_errors','1');
error_reporting(E_ALL);
$__logdir = __DIR__ . '/logs';
if (!is_dir($__logdir)) @mkdir($__logdir, 0775, true);
ini_set('error_log', $__logdir . '/php_error.log');
register_shutdown_function(function () use ($__logdir) {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        @file_put_contents($__logdir . '/fatal.log', date('c') . ' ' . json_encode($e, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
    }
});*/

session_start();

require_once __DIR__ . '/waConfig.php';
require_once __DIR__ . '/waBotUtils.php';

/**
 * =============== УТИЛИТЫ ВЕБ-СЕРВИСА ===============
 */
function ensure_dirs(): void {
    if (!is_dir($GLOBALS['ADMIN_LOGIN_LOG_DIR']))  @mkdir($GLOBALS['ADMIN_LOGIN_LOG_DIR'], 0775, true);
    if (!is_dir($GLOBALS['ADMIN_LOGIN_AUTH_DIR'])) @mkdir($GLOBALS['ADMIN_LOGIN_AUTH_DIR'], 0775, true);
}
function client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
function get_headers_safe(): array {
    if (function_exists('getallheaders')) {
        $h = getallheaders();
        if (is_array($h)) return $h;
    }
    $headers = [];
    foreach ($_SERVER as $k => $v) {
        if (strpos($k, 'HTTP_') === 0) {
            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
            $headers[$name] = $v;
        }
    }
    if (isset($_SERVER['CONTENT_TYPE']))   $headers['Content-Type']   = $_SERVER['CONTENT_TYPE'];
    if (isset($_SERVER['CONTENT_LENGTH'])) $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
    return $headers;
}
/** Определяем, что это именно Meta (а не браузер) */
function is_meta_request(array $headers): bool {
    $ua = $headers['User-Agent'] ?? $headers['User-agent'] ?? '';
    if (!empty($headers['X-Hub-Signature']) || !empty($headers['X-Hub-Signature-256'])) return true;
    if (isset($_GET['hub_verify_token']) || isset($_GET['hub.verify_token'])) return true;
    $ua_lc = strtolower($ua);
    foreach (['facebook','instagram','whatsapp','meta'] as $m) {
        if (strpos($ua_lc, $m) !== false) return true;
    }
    return false;
}
/** Логируем ТОЛЬКО meta-запросы */
function log_meta_request(string $raw): void {
    ensure_dirs();
    $headers = get_headers_safe();
    if (!is_meta_request($headers)) return;
    $entry = [
        'time'    => date('c'),
        'method'  => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'uri'     => $_SERVER['REQUEST_URI'] ?? '',
        'ip'      => client_ip(),
        'headers' => $headers,
        'get'     => $_GET,
        'post'    => $_POST,
        'body'    => $raw,
    ];
    @file_put_contents($GLOBALS['ADMIN_LOGIN_REQ_LOG'], json_encode($entry, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
}
function respond(int $code, string $body, string $type='text/html; charset=utf-8'): void {
    http_response_code($code);
    header('Content-Type: '.$type);
    echo $body;
    exit;
}
function is_locked(string $ip): array {
    ensure_dirs();
    $f = $GLOBALS['ADMIN_LOGIN_AUTH_DIR'] . '/' . preg_replace('~[^a-zA-Z0-9\.\-_:]~','_', $ip) . '.json';
    if (!is_file($f)) return ['locked'=>false,'fails'=>0,'until'=>0,'file'=>$f];
    $data = json_decode(@file_get_contents($f), true) ?: [];
    return [
        'locked'=> time() < (int)($data['until'] ?? 0),
        'fails' => (int)($data['fails'] ?? 0),
        'until' => (int)($data['until'] ?? 0),
        'file'  => $f
    ];
}
function record_fail(string $ip): void {
    $st = is_locked($ip);
    $fails = $st['fails'] + 1;
    $until = $st['until'];
    if ($fails >= (int)$GLOBALS['ADMIN_LOGIN_MAX_FAILS']) {
        $until = time() + (int)$GLOBALS['ADMIN_LOGIN_LOCK_SECONDS'];
        $fails = 0;
    }
    @file_put_contents($st['file'], json_encode(['fails'=>$fails, 'until'=>$until]));
}
function record_success(string $ip): void {
    $st = is_locked($ip);
    @file_put_contents($st['file'], json_encode(['fails'=>0, 'until'=>0]));
}
function html_head(string $title='Webhook'): string {
    return "<!doctype html><html lang='ru'><meta charset='utf-8'><title>{$title}</title>
<style>
 body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:24px;background:#0f1115;color:#e5e7eb}
 a{color:#93c5fd}
 .card{background:#17202a;border-radius:12px;padding:18px;box-shadow:0 8px 24px rgba(0,0,0,.35);margin-bottom:16px}
 code,pre{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;white-space:pre-wrap;word-wrap:break-word}
 .muted{opacity:.75}
 .grid{display:grid;grid-template-columns:1fr;gap:12px}
 .row{display:flex;gap:12px;flex-wrap:wrap}
 .pill{background:#1f2937;padding:4px 8px;border-radius:999px;font-size:12px}
 input,button{padding:10px 12px;border-radius:10px;border:1px solid #334155;background:#111827;color:#e5e7eb}
 form{display:flex;gap:10px;align-items:center}
</style>";
}
function tail_last_lines(string $file, int $lines = 100): array {
    if (!is_file($file)) return [];
    $fp = fopen($file, 'r'); if (!$fp) return [];
    $buffer = ''; $chunkSize = 8192; $pos = -1; $lineCount = 0;
    fseek($fp, 0, SEEK_END); $fileSize = ftell($fp);
    while ($lineCount <= $lines && -$pos < $fileSize) {
        $pos -= $chunkSize;
        fseek($fp, max(0, $fileSize + $pos));
        $chunk = fread($fp, min($chunkSize, $fileSize + $pos * -1));
        $buffer = $chunk . $buffer;
        $lineCount = substr_count($buffer, "\n");
    }
    fclose($fp);
    $arr = explode("\n", trim($buffer));
    $arr = array_filter($arr, fn($x) => $x !== '');
    return array_slice($arr, -$lines);
}

/**
 * =============== ЛОГИРУЕМ ТОЛЬКО META-ЗАПРОСЫ ===============
 */
$RAW_BODY = file_get_contents('php://input') ?? '';
log_meta_request($RAW_BODY);

/**
 * =============== VERIFY ДЛЯ META (GET hub.*) ===============
 */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && (isset($_GET['hub_verify_token']) || isset($_GET['hub.verify_token']))) {
    $token     = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
    $challenge = $_GET['hub_challenge']    ?? $_GET['hub.challenge']    ?? '';
    $mode      = $_GET['hub_mode']         ?? $_GET['hub.mode']         ?? '';
    if ($mode === 'subscribe' || $mode === '') {
        if ($token === ($GLOBALS['VERIFY_TOKEN'] ?? '')) {
            header('Content-Type: text/plain; charset=utf-8');
            echo $challenge;
            exit;
        }
        respond(403, "Invalid token", 'text/plain; charset=utf-8');
    }
}

/**
 * =============== ЛОГИН/ЛОГАУТ ДЛЯ ПРОСМОТРА ЛОГОВ ===============
 */
if (isset($_GET['logout'])) {
    unset($_SESSION['authed']);
    respond(200, html_head('Logout') . "<body><p>Вы вышли. <a href='?view=logs'>Войти снова</a></p></body></html>");
}
function login_screen(string $error=''): void {
    $head = html_head('Logs Login');
    $err  = $error ? "<p style='color:#fca5a5'>{$error}</p>" : "";
    respond(200, $head . "<body>
        <h2>Доступ к логам</h2>
        {$err}
        <form method='post' action='?view=logs'>
            <label>Пароль:</label>
            <input type='password' name='password' required>
            <button type='submit'>Войти</button>
        </form>
        <p class='muted'>Антибрут: 5 неверных попыток → блок на 5 минут.</p>
    </body></html>");
}
if (isset($_GET['view']) && $_GET['view'] === 'logs') {
    $ip = client_ip();
    $lock = is_locked($ip);
    if ($lock['locked']) {
        $wait = $lock['until'] - time();
        respond(429, html_head('Locked') . "<body><h3>Too Many Attempts</h3><p>IP заблокирован ещё на {$wait} сек.</p></body></html>");
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $pass = $_POST['password'] ?? '';
        if (hash_equals($GLOBALS['ADMIN_PASS'], $pass)) {
            $_SESSION['authed'] = true;
            record_success($ip);
            header('Location: ?view=logs');
            exit;
        }
        record_fail($ip);
        login_screen('Неверный пароль');
    }
    if (!($_SESSION['authed'] ?? false)) {
        login_screen('');
    }

    $lines = tail_last_lines($GLOBALS['ADMIN_LOGIN_REQ_LOG'], 100);
    $lines = array_reverse($lines);
    $itemsHtml = '';
    foreach ($lines as $line) {
        $j = json_decode($line, true);
        if (!$j) continue;
        $time = htmlspecialchars($j['time'] ?? '');
        $ipx  = htmlspecialchars($j['ip'] ?? '');
        $met  = htmlspecialchars($j['method'] ?? '');
        $uri  = htmlspecialchars($j['uri'] ?? '');
        $pretty = json_encode($j, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $itemsHtml .= "<div class='card'>
            <div class='row'>
                <span class='pill'>{$met}</span>
                <span class='pill'>{$ipx}</span>
                <span class='pill muted'>{$time}</span>
            </div>
            <div class='muted' style='margin:8px 0'>".htmlspecialchars($uri)."</div>
            <pre><code>".htmlspecialchars($pretty)."</code></pre>
        </div>";
    }
    $head = html_head('Webhook Logs');
    respond(200, $head . "<body>
        <h2>Последние 100 событий (новые сверху)</h2>
        <p class='muted'>Показываются только запросы, определённые как Meta (Facebook/Instagram/WhatsApp). Браузер и логин не логируются.</p>
        <p><a href='?logout=1'>Выйти</a></p>
        <div class='grid'>{$itemsHtml}</div>
    </body></html>");
}

/**
 * =============== БАЗОВАЯ СТРАНИЦА (ручной GET) ===============
 */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $head = html_head('Webhook');
    respond(200, $head . "<body>
        <h2>✅ Webhook работает</h2>
        <p class='muted'>Эндпойнт принимает события от WhatsApp / Messenger / Instagram.</p>
        <ul>
            <li>Верификация: GET c <code>hub.verify_token</code> и <code>hub.challenge</code>.</li>
            <li>Логи: <code>?view=logs</code> (вход по паролю). Браузер/логин не логируются.</li>
        </ul>
    </body></html>");
}

/**
 * =============== ОБРАБОТКА СОБЫТИЙ (POST) ===============
 */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // 1) мгновенный ответ клиенту
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);

    // Отсоединяемся от клиента
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        @ob_end_flush(); @flush(); @ob_flush();
    }

    // 2) асинхронная обработка
    try {
        $fmt = format_incoming($RAW_BODY); // определит платформу, соберет текст и id

        // Если не настоящие входящие — выходим
        if (!($fmt['ok'] ?? false) || empty($fmt['message_ids'])) {
            @file_put_contents($GLOBALS['ADMIN_LOGIN_LOG_DIR'] . '/formatted.log', "[" . date('c') . "]\n(нет входящих) " . ($fmt['text'] ?? '') . "\n\n", FILE_APPEND);
            exit;
        }

        // Анти-loop для WhatsApp: если отправитель совпадает с номером пересылки — не эхаем
        if (($fmt['platform'] ?? '') === 'whatsapp') {
            $fromNorm = preg_replace('~\D+~','', $fmt['sender_id'] ?? '');
            $toNorm   = preg_replace('~\D+~','', $GLOBALS['FORWARD_TO'] ?? '');
            if ($fromNorm !== '' && $fromNorm === $toNorm) exit;
        }

        // Анти-дубликаты: если ВСЕ ids уже видели — не пересылать
        $allSeen = true;
        foreach ($fmt['message_ids'] as $mid) {
            if (!bu_seen_event($mid)) $allSeen = false;
        }
        if ($allSeen) exit;

        // Форматированный текст в лог
        @file_put_contents($GLOBALS['ADMIN_LOGIN_LOG_DIR'] . '/formatted.log', "[" . date('c') . "]\n" . ($fmt['text'] ?? '') . "\n\n", FILE_APPEND);

        // Пересылка на ваш WhatsApp
        $ok = forward_with_fallback(
            $GLOBALS['GRAPH_VERSION'],
            $GLOBALS['PHONE_NUMBER_ID'],
            $GLOBALS['ACCESS_TOKEN'],
            $GLOBALS['FORWARD_TO'],
            $fmt['text'],
            'hello_world', // шаблон на случай 24h окна
            'en_US'
        );
        if (!$ok) {
            bu_log($GLOBALS['ADMIN_LOGIN_LOG_DIR'] . '/forward_errors.log', ['reason' => 'forward_with_fallback failed', 'platform'=>$fmt['platform'] ?? '']);
        }
    } catch (Throwable $e) {
        bu_log($GLOBALS['ADMIN_LOGIN_LOG_DIR'] . '/forward_errors.log', ['exception' => $e->getMessage()]);
    }
    exit;
}

respond(405, "Method Not Allowed", 'text/plain; charset=utf-8');
