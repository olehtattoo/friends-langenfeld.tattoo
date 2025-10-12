<?php declare(strict_types=1);

require_once __DIR__ . '/waDebug/waCrashCatcher.php';
require_once __DIR__ . '/waDebug/waLogDebug.php';

require_once __DIR__ . '/waConfig/waConfig.php';
require_once __DIR__ . '/waBotUtils.php';





session_start();

/**
 * =============== УТИЛИТЫ ВЕБ-СЕРВИСА ===============
 */

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}


/**
 * Возвращает безопасный список HTTP-заголовков как ассоциативный массив.
 *
 * Логика работы:
 *  1) Если доступна функция getallheaders() и она вернула массив — используем его.
 *  2) Иначе восстанавливаем заголовки из $_SERVER: все ключи с префиксом HTTP_
 *     преобразуются к виду "Header-Name" (каждое слово с заглавной буквы, дефисы вместо подчёркиваний).
 *  3) Дополнительно добавляем CONTENT_TYPE и CONTENT_LENGTH, т.к. они обычно приходят без префикса HTTP_.
 *
 * Особенности и ограничения:
 *  - При дублирующихся заголовках значение будет перезаписано последним встреченным.
 *  - Ключи массива чувствительны к регистру так, как сформированы (формат "Header-Name").
 *
 * @return array<string,string> Ассоциативный массив вида ['Header-Name' => 'value'].
 */
function get_headers_safe(): array
{
    if (function_exists('getallheaders')) {
        $h = getallheaders();
        if (is_array($h))
            return $h;
    }
    $headers = [];
    foreach ($_SERVER as $k => $v) {
        if (strpos($k, 'HTTP_') === 0) {
            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
            $headers[$name] = $v;
        }
    }
    if (isset($_SERVER['CONTENT_TYPE']))
        $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
    if (isset($_SERVER['CONTENT_LENGTH']))
        $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
    return $headers;
}

/** Определяем, что это именно Meta (а не браузер) */
function is_meta_request(array $headers): bool
{
    $ua = $headers['User-Agent'] ?? $headers['User-agent'] ?? '';
    if (!empty($headers['X-Hub-Signature']) || !empty($headers['X-Hub-Signature-256']))
        return true;
    if (isset($_GET['hub_verify_token']) || isset($_GET['hub.verify_token']))
        return true;
    $ua_lc = strtolower($ua);
    foreach (['facebook', 'instagram', 'whatsapp', 'meta'] as $m) {
        if (strpos($ua_lc, $m) !== false)
            return true;
    }
    return false;
}

function respond(int $code, string $body, string $type = 'text/html; charset=utf-8'): void
{
    http_response_code($code);
    header('Content-Type: ' . $type);
    echo $body;
    exit;
}


function html_head(string $title = 'Webhook'): string
{
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



/**
 * Возвращает последние N строк файла.
 * Подходит для быстрого просмотра хвоста логов. Если файл недоступен — вернёт [].
 *
 * @param string $file  Путь к файлу.
 * @param int    $lines Количество строк с конца (по умолчанию 100).
 * @return array Последние строки (без пустых), в обычном порядке.
 */
function tail_last_lines(string $file, int $lines = 100): array
{
    if (!is_file($file))
        return [];
    $fp = fopen($file, 'r');
    if (!$fp)
        return [];
    $buffer = '';
    $chunkSize = 8192;
    $pos = -1;
    $lineCount = 0;
    fseek($fp, 0, SEEK_END);
    $fileSize = ftell($fp);
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
    $token = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';
    $mode = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '';
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
function login_screen(string $error = ''): void
{
    $head = html_head('Logs Login');
    $err = $error ? "<p style='color:#fca5a5'>{$error}</p>" : "";
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
    /*$lock = is_locked($ip);
    if ($lock['locked']) {
        $wait = $lock['until'] - time();
        respond(429, html_head('Locked') . "<body><h3>Too Many Attempts</h3><p>IP заблокирован ещё на {$wait} сек.</p></body></html>");
    }*/
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $pass = $_POST['password'] ?? '';
        if (hash_equals($GLOBALS['ADMIN_PASS'], $pass)) {
            $_SESSION['authed'] = true;
            /*record_success($ip);*/
            header('Location: ?view=logs');
            exit;
        }
        /*record_fail($ip);*/
        login_screen('Неверный пароль');
    }
    if (!($_SESSION['authed'] ?? false)) {
        login_screen('');
    }

    $lines = tail_last_lines($GLOBALS['WA_MAIN_LOG'], 100);
    $lines = array_reverse($lines);
    $itemsHtml = '';
    foreach ($lines as $line) {

        $j = json_decode($line, true);
        if (!$j)
            continue;

        // Если это наша компактная запись
        if (($j['message'] ?? '') === 'META EVT' && is_array($j['ctx'] ?? null)) {
            $itemsHtml .= render_meta_event_card($j['ctx']);
            continue;
        }

        $time = htmlspecialchars($j['time'] ?? '');
        $ipx = htmlspecialchars($j['ip'] ?? '');
        $met = htmlspecialchars($j['method'] ?? '');
        $uri = htmlspecialchars($j['uri'] ?? '');
        $pretty = json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $itemsHtml .= "<div class='card'>
            <div class='row'>
                <span class='pill'>{$met}</span>
                <span class='pill'>{$ipx}</span>
                <span class='pill muted'>{$time}</span>
            </div>
            <div class='muted' style='margin:8px 0'>" . htmlspecialchars($uri) . "</div>
            <pre><code>" . htmlspecialchars($pretty) . "</code></pre>
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
        //wa_log_info('FastCGI finish request');
        fastcgi_finish_request();
    } else {
        //wa_log_info('Close hook connection! PHP ob_end_flush/flush/ob_flush');
        /*@ob_end_flush();*/
        @flush(); /*@ob_flush();*/
    }

    // 2) асинхронная обработка
    try {
        $fmt = format_incoming($RAW_BODY); // определит платформу, соберет текст и id

        /*wa_log_info('Formatted incoming', [
            'ok' => $fmt['ok'] ?? false,
            'platform' => $fmt['platform'] ?? '',
            'sender_id' => $fmt['sender_id'] ?? '',
            'message_ids' => $fmt['message_ids'] ?? [],
            'text' => $fmt['text'] ?? '',
        ]);*/

        // Если не настоящие входящие — выходим
        if (!($fmt['ok'] ?? false) || empty($fmt['message_ids'])) {
            //@file_put_contents($GLOBALS['ADMIN_LOGIN_LOG_DIR'] . '/formatted.log', "[" . date('c') . "]\n(нет входящих) " . ($fmt['text'] ?? '') . "\n\n", FILE_APPEND);
            wa_log_error('No incoming messages', [
                'formatted' => $fmt,
            ]);
            exit;
        }

        // Анти-loop для WhatsApp: если отправитель совпадает с номером пересылки — не эхаем
        //так же обработка комманд с админ телефона $GLOBALS['FORWARD_TO']
        if (($fmt['platform'] ?? '') === 'whatsapp') {
            wa_log_info('Anti-loop check for WhatsApp');
            $fromNorm = preg_replace('~\D+~', '', $fmt['sender_id'] ?? '');
            $toNorm = preg_replace('~\D+~', '', $GLOBALS['FORWARD_TO'] ?? '');
            if ($fromNorm !== '' && $fromNorm === $toNorm) {
                // ПЕРЕД exit обрабатываем «обратное» сообщение с кодом
                try {
                    wa_log_info('Anti-loop check passed, handling own message code', [
                        'from' => $fromNorm,
                        'to' => $toNorm,
                    ]);
                    // ===== Own-command (короткий дебаг) =====
                    wa_log_info('OWNCMD start: text="' . $fmt['text'] ?? '');

                    $cmd = wa_bot_handle_own_message_code($fmt, $RAW_BODY);
                    wa_log_info(
                        'OWNCMD parsed: ok=' . (int) ($cmd['ok'] ?? 0) .
                        ' code=' . ($cmd['code'] ?? '') .
                        ' num=' . (int) ($cmd['command_num'] ?? 0) .
                        ' key=' . (string) ($cmd['command_key'] ?? '') .
                        ' has_text=' . (int) ($cmd['has_text'] ?? 0)
                    );

                    if (!empty($cmd['ok'])) {
                        $result = wa_bot_process_own_command($cmd);
                        wa_log_info(
                            'OWNCMD done: sent=' . (int) ($result['sent'] ?? 0) .
                            ' ok=' . (int) ($result['ok'] ?? 0) .
                            ' http=' . (string) ($result['http_code'] ?? 'null') .
                            ' err=' . (string) ($result['error'] ?? '')
                        );
                    } else {
                        wa_log_info('OWNCMD skip (no valid command)');
                    }
                } catch (Throwable $e) {
                    wa_log_error('own-message-code handler failed', ['err' => $e->getMessage()]);
                }
                exit;
            }
            // ...
        }

        /* 
        wa_log_info('Formatted text', [
             'text' => $fmt['text'] ?? '',
         ]);
         */

        // Пересылка на ваш WhatsApp
        $ok = forward_with_fallback(
            $GLOBALS['GRAPH_VERSION'],
            $GLOBALS['PHONE_NUMBER_ID'],
            $GLOBALS['ACCESS_TOKEN'],
            $GLOBALS['FORWARD_TO'],
            $fmt['text'],
            $GLOBALS['TEMPLATE_NAME'],
            $GLOBALS['TEMPLATE_LANG'],
            $fmt['sender_id']              // <-- вот это добавили
        );

        wa_log_info('FORWARD ctx', [
            'from' => $fmt['sender_id'] ?? '',
            'to' => $GLOBALS['FORWARD_TO'] ?? '',
        ]);

        if (!$ok) {
            wa_log_error('forward_with_fallback failed', [
                'reason' => 'forward_with_fallback failed',
                'platform' => $fmt['platform'] ?? '',
            ]);
        } else {
            /*
            wa_log_info('Message forwarded successfully', [
                'platform' => $fmt['platform'] ?? '',
                'to' => $GLOBALS['FORWARD_TO'],
            ]);
            */
        }
    } catch (Throwable $e) {
        wa_log_error('Exception in webhook processing', [
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
    exit;
}

respond(405, "Method Not Allowed", 'text/plain; charset=utf-8');
