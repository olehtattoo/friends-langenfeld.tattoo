<?php declare(strict_types=1);

/**
 * Единый логгер для всего проекта.
 * Пишет JSONL в $WA_MAIN_LOG при $WA_MAIN_LOG_DEBUG === true.
 * При ошибках записи пишет причину в error_log.
 */

require_once __DIR__ . '/../waConfig/waConfig.php';

require_once __DIR__ . '/waCrashCatcher.php';

/** ================= ВНУТРЕННИЕ УТИЛИТЫ ================= */

if (!function_exists('wa__log_path')) {
    function wa__log_path(): string {
        $path = $GLOBALS['WA_MAIN_LOG'];
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
        return wa_log('INFO:', $message, $context);
    }
}
if (!function_exists('wa_log_warn')) {
    function wa_log_warn(string $message, array $context = []): bool {
        return wa_log('WARN:', $message, $context);
    }
}
if (!function_exists('wa_log_error')) {
    function wa_log_error(string $message, array $context = []): bool {
        return wa_log('ERROR:', $message, $context);
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


/**
 * Компактное логирование Meta-ивентов (WA / IG / FB).
 * Пишет по одной записи на каждое атомарное событие.
 *
 * wa_log_info('META EVT', $event);
 * где $event имеет поля: kind, platform, field, dir, from, to, id, status/type, text (усеч.), conv, ts, entry_id, raw? (для unknown)
 */

/** Усечение строки (UTF-8) с многоточием. */
function _cut(string $s, int $max): string {
    if ($max <= 0) return $s;
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return (mb_strlen($s,'UTF-8')>$max) ? rtrim(mb_substr($s,0,max(1,$max-1),'UTF-8')).'…' : $s;
    }
    return (strlen($s)>$max) ? rtrim(substr($s,0,max(1,$max-1))).'…' : $s;
}

/** Безопасный геттер. */
function _gx(array $a, string $path, $def = null) {
    $cur = $a;
    foreach (explode('.', $path) as $k) {
        if (!is_array($cur) || !array_key_exists($k, $cur)) return $def;
        $cur = $cur[$k];
    }
    return $cur;
}

/** Разворачивает payload Meta в список компактных событий. */
function _meta_extract_events(array $p): array {
    $out = [];
    $object = (string)($p['object'] ?? '');      // 'whatsapp_business_account' | 'page' | 'instagram'
    $entries = $p['entry'] ?? [];
    foreach ($entries as $entry) {
        $entryId = (string)($entry['id'] ?? '');
        $tsNow   = date('c');

        /* ===== WhatsApp (Cloud / WABA): entry[].changes[].value */
        if ($object === 'whatsapp_business_account') {
            foreach (($entry['changes'] ?? []) as $chg) {
                $field = (string)($chg['field'] ?? ''); // 'messages' / 'business_capability_update' / ...
                $val   = $chg['value'] ?? [];
                $platform = 'whatsapp';

                // messages: inbound user messages
                foreach (($val['messages'] ?? []) as $m) {
                    $type = (string)($m['type'] ?? '');
                    $from = (string)($m['from'] ?? ''); // wa_id клиента
                    $to   = (string)_gx($val, 'metadata.phone_number_id', '');
                    $id   = (string)($m['id'] ?? '');
                    $text = '';
                    if ($type === 'text')           $text = (string)($m['text']['body'] ?? '');
                    if ($type === 'interactive') { // button_reply / list_reply
                        if (!empty($m['interactive']['button_reply'])) {
                            $br = $m['interactive']['button_reply'];
                            $text = '[button] ' . (($br['title'] ?? '') . ' {' . ($br['id'] ?? '') . '}');
                        } elseif (!empty($m['interactive']['list_reply'])) {
                            $lr = $m['interactive']['list_reply'];
                            $text = '[list] ' . (($lr['title'] ?? '') . ' {' . ($lr['id'] ?? '') . '}');
                        }
                    }
                    $out[] = [
                        'kind'      => 'message',
                        'platform'  => $platform,
                        'field'     => $field,
                        'dir'       => 'in',
                        'from'      => $from,
                        'to'        => $to,
                        'id'        => $id,
                        'type'      => $type,
                        'text'      => _cut((string)$text, 160),
                        'conv'      => (string)_gx($val,'conversation.id',''),
                        'ts'        => (string)($m['timestamp'] ?? $tsNow),
                        'entry_id'  => $entryId,
                    ];
                }

                // statuses: sent/delivered/read/failed for your outbound messages
                foreach (($val['statuses'] ?? []) as $st) {
                    $out[] = [
                        'kind'      => 'status',
                        'platform'  => $platform,
                        'field'     => $field,
                        'dir'       => 'out', // статус исходит от WA на исходящее сообщение
                        'from'      => (string)_gx($val,'metadata.phone_number_id',''), // ваш номер (phone_number_id)
                        'to'        => (string)($st['recipient_id'] ?? ''),
                        'id'        => (string)($st['id'] ?? ''),  // message id (wamid.*)
                        'status'    => (string)($st['status'] ?? ''), // sent|delivered|read|failed
                        'conv'      => (string)_gx($st,'conversation.id',''),
                        'error'     => _gx($st,'errors.0.code', null) ? ('#'._gx($st,'errors.0.code', '').' '._cut((string)_gx($st,'errors.0.title',''),60)) : null,
                        'ts'        => (string)($st['timestamp'] ?? $tsNow),
                        'entry_id'  => $entryId,
                    ];
                }

                // прочие WABA fields (business_capability_update, business_status_update, account_alerts, calls, flows, automatic_events...)
                if (empty($val['messages']) && empty($val['statuses'])) {
                    $out[] = [
                        'kind'      => 'waba_event',
                        'platform'  => $platform,
                        'field'     => $field,
                        'dir'       => 'sys',
                        'from'      => '',
                        'to'        => '',
                        'id'        => (string)$entryId,
                        'status'    => (string)_gx($val,'event',''), // иногда бывает поле event
                        'text'      => _cut(json_encode($val, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), 300),
                        'ts'        => $tsNow,
                        'entry_id'  => $entryId,
                    ];
                }
            }
            continue;
        }

        /* ===== Messenger / Instagram Messaging: entry[].messaging[] */
        if (!empty($entry['messaging'])) {
            foreach ($entry['messaging'] as $m) {
                $isIG = !empty($m['recipient']) && isset($m['recipient']['id']) && (strpos((string)$m['recipient']['id'], 'ig') === 0 || strpos((string)$m['sender']['id'], 'ig') === 0);
                $platform = $isIG ? 'instagram' : 'messenger';

                $base = [
                    'platform' => $platform,
                    'field'    => 'messaging',
                    'from'     => (string)_gx($m, 'sender.id', ''),
                    'to'       => (string)_gx($m, 'recipient.id', ''),
                    'ts'       => (string)($m['timestamp'] ?? $tsNow),
                    'entry_id' => $entryId,
                ];

                if (isset($m['message'])) {
                    $msg  = $m['message'];
                    $text = (string)($msg['text'] ?? '');
                    if (empty($text) && !empty($msg['attachments'])) {
                        $types = array_unique(array_map(fn($a)=>(string)($a['type']??''), $msg['attachments']));
                        $text = '[attachments] '.implode(',', $types);
                    }
                    $out[] = array_merge($base, [
                        'kind' => 'message',
                        'dir'  => 'in',
                        'id'   => (string)($msg['mid'] ?? ''),
                        'type' => !empty($msg['is_echo']) ? 'echo' : 'text',
                        'text' => _cut($text, 160),
                    ]);
                } elseif (isset($m['postback'])) {
                    $pb = $m['postback'];
                    $out[] = array_merge($base, [
                        'kind'   => 'postback',
                        'dir'    => 'in',
                        'id'     => (string)($pb['mid'] ?? ''),
                        'type'   => 'postback',
                        'text'   => _cut((string)($pb['title'] ?? ''), 80),
                        'payload'=> _cut((string)($pb['payload'] ?? ''), 120),
                    ]);
                } elseif (isset($m['read'])) {
                    $out[] = array_merge($base, [
                        'kind'   => 'read',
                        'dir'    => 'out',
                        'id'     => '',
                        'status' => 'read',
                        'watermark' => (int)_gx($m,'read.watermark',0),
                    ]);
                } elseif (isset($m['delivery'])) {
                    $d = $m['delivery'];
                    $out[] = array_merge($base, [
                        'kind'   => 'delivery',
                        'dir'    => 'out',
                        'id'     => implode(',', (array)($d['mids'] ?? [])),
                        'status' => 'delivered',
                    ]);
                } elseif (isset($m['reaction'])) {
                    $r = $m['reaction'];
                    $out[] = array_merge($base, [
                        'kind'   => 'reaction',
                        'dir'    => 'in',
                        'id'     => (string)($r['mid'] ?? ''),
                        'type'   => (string)($r['action'] ?? ''), // react|unreact
                        'text'   => (string)($r['emoji'] ?? ''),
                    ]);
                } elseif (isset($m['standby'])) {
                    $out[] = array_merge($base, [
                        'kind'   => 'standby',
                        'dir'    => 'sys',
                        'id'     => '',
                        'status' => 'standby',
                    ]);
                } else {
                    $out[] = array_merge($base, [
                        'kind' => 'unknown',
                        'dir'  => 'sys',
                        'id'   => '',
                        'text' => _cut(json_encode($m, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), 300),
                    ]);
                }
            }
            continue;
        }

        /* ===== Instagram (changes.value.* – на всякий случай) */
        foreach (($entry['changes'] ?? []) as $chg) {
            $val = $chg['value'] ?? [];
            if (!empty($val['messages'])) {
                foreach ($val['messages'] as $msg) {
                    $out[] = [
                        'kind'     => 'message',
                        'platform' => 'instagram',
                        'field'    => (string)($chg['field'] ?? 'messages'),
                        'dir'      => 'in',
                        'from'     => (string)($msg['from'] ?? ''),
                        'to'       => (string)_gx($val,'recipient',''),
                        'id'       => (string)($msg['id'] ?? ''),
                        'type'     => (string)($msg['type'] ?? ''),
                        'text'     => _cut((string)_gx($msg,'text',''),160),
                        'ts'       => (string)_gx($msg,'timestamp', $tsNow),
                        'entry_id' => $entryId,
                    ];
                }
            } else {
                // неизвестное обновление IG
                $out[] = [
                    'kind'     => 'unknown',
                    'platform' => 'instagram',
                    'field'    => (string)($chg['field'] ?? ''),
                    'dir'      => 'sys',
                    'from'     => '',
                    'to'       => '',
                    'id'       => (string)$entryId,
                    'text'     => _cut(json_encode($val, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), 300),
                    'ts'       => $tsNow,
                    'entry_id' => $entryId,
                ];
            }
        }
    }

    // если ничего не распознали
    if (!$out) {
        $out[] = [
            'kind'     => 'unknown',
            'platform' => $object ?: 'meta',
            'field'    => '',
            'dir'      => 'sys',
            'from'     => '',
            'to'       => '',
            'id'       => '',
            'text'     => _cut(json_encode($p, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), 500),
            'ts'       => date('c'),
            'entry_id' => '',
        ];
    }
    return $out;
}

/** Логируем ТОЛЬКО meta-запросы — компактно, по одному событию на строку. */
function log_meta_request(string $raw): void
{
    $headers = get_headers_safe();
    if (!is_meta_request($headers)) return;

    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        wa_log_info('META EVT', [
            'kind'     => 'unknown',
            'platform' => 'meta',
            'dir'      => 'sys',
            'text'     => _cut($raw, 500),
            'ts'       => date('c'),
        ]);
        return;
    }

    foreach (_meta_extract_events($payload) as $evt) {
        // добавим минимум транспорта: метод/URI/IP
        $evt['method'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $evt['uri']    = $_SERVER['REQUEST_URI'] ?? '';
        $evt['ip']     = client_ip();
        wa_log_info('META EVT', $evt);
    }
}

/* ================== HTML-рендер карточки события (для /?view=logs) ================== */
/**
 * Возвращает готовый HTML блока события (используйте в списке логов вместо raw JSON).
 * Ожидает лог-строку, где message == 'META EVT' и ctx содержит поля из _meta_extract_events().
 */
function render_meta_event_card(array $ctx): string
{
    $badge = function(string $txt, string $muted=''): string {
        $c = $muted ? 'pill muted' : 'pill';
        return "<span class='{$c}'>".htmlspecialchars($txt)."</span>";
    };
    $line = function(string $k, string $v): string {
        $k = htmlspecialchars($k); $v = htmlspecialchars($v);
        return "<div class='row' style='gap:8px'><span class='muted' style='min-width:110px'>{$k}</span><code>{$v}</code></div>";
    };

    $h  = "<div class='card'>";
    $top = [];
    if (!empty($ctx['platform'])) $top[] = strtoupper($ctx['platform']);
    if (!empty($ctx['kind']))     $top[] = $ctx['kind'];
    if (!empty($ctx['field']))    $top[] = $ctx['field'];
    if (!empty($ctx['dir']))      $top[] = ($ctx['dir']==='in'?'IN':'OUT');
    $h .= "<div class='row'>".$badge(implode(' · ', $top)).$badge($ctx['method'] ?? 'POST').$badge($ctx['ip'] ?? '').$badge($ctx['ts'] ?? '', 'muted')."</div>";

    $h .= "<div class='muted' style='margin:6px 0'>".htmlspecialchars($ctx['uri'] ?? '')."</div>";

    // Основные поля
    $pairs = [
        'from' => $ctx['from'] ?? '',
        'to'   => $ctx['to'] ?? '',
        'id'   => $ctx['id'] ?? '',
    ];
    if (!empty($ctx['status'])) $pairs['status'] = $ctx['status'];
    if (!empty($ctx['type']))   $pairs['type']   = $ctx['type'];
    if (!empty($ctx['conv']))   $pairs['conv']   = $ctx['conv'];
    foreach ($pairs as $k=>$v) if ($v!=='') $h .= $line($k, (string)$v);

    if (!empty($ctx['text'])) {
        $h .= "<pre><code>".htmlspecialchars($ctx['text'])."</code></pre>";
    }

    $h .= "</div>";
    return $h;
}
