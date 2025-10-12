<?php declare(strict_types=1);

require_once __DIR__ . '/waDebug/waCrashCatcher.php';
require_once __DIR__ . '/waDebug/waLogDebug.php';

require_once __DIR__ . '/waConfig/waConfig.php';
require_once __DIR__ . '/waConfig/waConfigMessage.php';

require_once __DIR__ . '/waDbTools/waDbHelper.php';

/**
 * ----- БАЗОВЫЕ УТИЛИТЫ -----
 */


/** Нормализация телефона: цифры (Cloud API допускает без '+') */
function bu_msisdn(string $phone): string
{
    return preg_replace('~\D+~', '', $phone);
}

// === Глобальные обработчики ошибок/исключений/фаталов ===
if (!function_exists('wa_install_error_handlers')) {
    function wa_install_error_handlers(): void
    {
        // Всё логируем, но не подавляем нативное поведение PHP
        set_error_handler(function (int $severity, string $message, string $file = '', int $line = 0) {
            wa_log_error('PHP error', compact('severity', 'message', 'file', 'line'));
            return false; // вернуть false = отдать дальше PHP (стандартно)
        });

        set_exception_handler(function (Throwable $e) {
            wa_log_error('Uncaught exception', [
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 4000),
            ]);
            // Ничего не выводим, чтобы не светить детали пользователю
            http_response_code(500);
        });

        register_shutdown_function(function () {
            $e = error_get_last();
            if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
                wa_log_error('PHP Fatal shutdown', $e);
            }
        });

        // На время отладки можно включить показ ошибок в браузер (потом выключи!)
        if (isset($GLOBALS['WA_MAIN_LOG_DEBUG']) && $GLOBALS['WA_MAIN_LOG_DEBUG']) {
            @ini_set('display_errors', '1');
            @ini_set('display_startup_errors', '1');
            @error_reporting(E_ALL);
        }
    }
    wa_install_error_handlers();
}


/**
 * ----- ФОРМАТИРОВАНИЕ ВХОДЯЩИХ СООБЩЕНИЙ -----
 * Универсальный форматтер для WhatsApp / Messenger / Instagram.
 * Возвращает массив:
 *   [
 *     'ok'          => bool,
 *     'platform'    => 'whatsapp'|'messenger'|'instagram'|'unknown',
 *     'text'        => string,   // аккуратный читаемый текст
 *     'message_ids' => string[], // список msg-id для антидубликатов
 *     'sender_id'   => string,   // основной отправитель первого сообщения
 *     'sender_name' => string    // если известно
 *   ]
 */
function format_incoming(string $raw): array
{
    $p = json_decode($raw, true);
    wa_log_info('Format_incoming function:', $p);

    if (!$p || !isset($p['entry'][0])) {
        return ['ok' => false, 'platform' => 'unknown', 'text' => '⚠️ Некорректный webhook payload.', 'message_ids' => [], 'sender_id' => '', 'sender_name' => ''];
    }

    $object = strtolower(trim((string) ($p['object'] ?? '')));
    if ($object === 'whatsapp_business_account') {
        return format_whatsapp_payload($p);
    } elseif ($object === 'page') {
        // Facebook Page Webhooks → Messenger (DM). Иногда сюда приходят и IG Connected Inboxes, но структура та же.
        return format_messenger_payload($p);
    } elseif ($object === 'instagram') {
        return format_instagram_payload($p);
    }
    return ['ok' => false, 'platform' => 'unknown', 'text' => 'ℹ️ Неизвестный объект webhook: ' . $object, 'message_ids' => [], 'sender_id' => '', 'sender_name' => ''];
}

/** Проверка: есть ли ВХОДЯЩИЕ сообщения (а не статусы/доставки/echo) */
function has_incoming_messages_any(string $raw): bool
{
    $res = format_incoming($raw);
    return $res['ok'] && !empty($res['message_ids']);
}

/**
 * ----- WhatsApp -----
 */
function format_whatsapp_payload(array $payload): array
{
    $outChunks = [];
    $ids = [];
    $firstSender = '';
    $firstName = '';

    foreach ($payload['entry'] as $entry) {
        foreach (($entry['changes'] ?? []) as $chg) {
            $value = $chg['value'] ?? [];
            $meta = $value['metadata'] ?? [];
            $biz_num = $meta['display_phone_number'] ?? '';
            $biz_wa = $meta['phone_number_id'] ?? '';

            // Если нет входящих messages — пропускаем (statuses, delivery/read)
            $messages = $value['messages'] ?? [];
            if (!$messages)
                continue;

            // Сопоставим имена
            $names = [];
            foreach (($value['contacts'] ?? []) as $c) {
                $wa = $c['wa_id'] ?? '';
                $nm = $c['profile']['name'] ?? '';
                if ($wa)
                    $names[$wa] = $nm;
            }

            foreach ($messages as $m) {
                $from = $m['from'] ?? '';
                $name = $names[$from] ?? ($m['profile']['name'] ?? '');
                if ($firstSender === '') {
                    $firstSender = $from;
                    $firstName = $name;
                }

                $ts = isset($m['timestamp']) ? date('Y-m-d H:i:s', (int) $m['timestamp']) : date('Y-m-d H:i:s');
                $type = $m['type'] ?? 'unknown';
                $mid = $m['id'] ?? '';
                if ($mid)
                    $ids[] = $mid;

                $lines = [];
                $lines[] = "📥 Новое сообщение WhatsApp";

                //if ($biz_num || $biz_wa) $lines[] = "🏢 На номер студии: {$biz_num} ({$biz_wa})";

                $who = trim(($name ? "{$name} " : '') . "({$from})");
                $lines[] = "👤 От: {$who}";
                $lines[] = "🕒 Время: {$ts}";
                $lines[] = "📨 Тип: {$type}";

                if ($type === 'text' && isset($m['text']['body'])) {
                    $lines[] = "💬 Текст:\n" . $m['text']['body'];
                } elseif ($type === 'button' && !empty($m['button'])) {
                    $t = $m['button']['text'] ?? '';
                    $p = $m['button']['payload'] ?? '';
                    $lines[] = "🔘 Кнопка: {$t}" . ($p ? " (payload: {$p})" : "");
                } elseif ($type === 'interactive' && !empty($m['interactive'])) {
                    $i = $m['interactive'];
                    if (!empty($i['button_reply'])) {
                        $t = $i['button_reply']['title'] ?? '';
                        $iD = $i['button_reply']['id'] ?? '';
                        $lines[] = "🔘 Ответ кнопкой: {$t}" . ($iD ? " [id: {$iD}]" : "");
                    } elseif (!empty($i['list_reply'])) {
                        $t = $i['list_reply']['title'] ?? '';
                        $iD = $i['list_reply']['id'] ?? '';
                        $lines[] = "📋 Выбор из списка: {$t}" . ($iD ? " [id: {$iD}]" : "");
                    } else {
                        $lines[] = "ℹ️ interactive payload:\n" . json_encode($i, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                }

                if ($type === 'location' && !empty($m['location'])) {
                    $loc = $m['location'];
                    $lines[] = "📍 Локация: {$loc['latitude']}, {$loc['longitude']}"
                        . (!empty($loc['name']) ? "\nНазвание: {$loc['name']}" : "")
                        . (!empty($loc['address']) ? "\nАдрес: {$loc['address']}" : "");
                }

                // Вложения
                $attachLines = [];
                $addAtt = function (string $label, array $d) use (&$attachLines) {
                    $mime = $d['mime_type'] ?? '';
                    $id = $d['id'] ?? '';
                    $cap = $d['caption'] ?? '';
                    $name = $d['filename'] ?? '';
                    $extra = array_values(array_filter([$mime, $name]));
                    $head = "📎 {$label}" . ($extra ? " [" . implode(' | ', $extra) . "]" : "");
                    $tail = $id ? " (media_id: {$id})" : "";
                    $line = $head . $tail;
                    if ($cap)
                        $line .= "\n   └─ caption: {$cap}";
                    $attachLines[] = $line;
                };
                foreach (['image' => 'Изображение', 'video' => 'Видео', 'audio' => 'Аудио', 'document' => 'Документ', 'sticker' => 'Стикер'] as $k => $label) {
                    if ($type === $k && !empty($m[$k]))
                        $addAtt($label, $m[$k]);
                }
                if ($attachLines)
                    $lines[] = implode("\n", $attachLines);

                if (!empty($m['context']['id']))
                    $lines[] = "↩️ Ответ на msg_id: " . $m['context']['id'];

                $outChunks[] = implode("\n", $lines);
            }
        }
    }

    $text = $outChunks ? implode("\n\n" . str_repeat("—", 32) . "\n\n", $outChunks) : '';
    return [
        'ok' => !empty($ids),
        'platform' => 'whatsapp',
        'text' => $text ?: "ℹ️ События получены, но входящих сообщений не обнаружено.",
        'message_ids' => array_values(array_unique($ids)),
        'sender_id' => $firstSender,
        'sender_name' => $firstName,
    ];
}

/**
 * ----- Messenger (Facebook Page) -----
 * object: "page", entry[*].messaging[*]
 */
function format_messenger_payload(array $payload): array
{
    $outChunks = [];
    $ids = [];
    $firstSender = '';
    $firstName = ''; // у Messenger имена не приходят напрямую без Graph lookup

    foreach ($payload['entry'] as $entry) {
        $pageId = $entry['id'] ?? '';
        foreach (($entry['messaging'] ?? []) as $m) {
            // delivery/read/echo пропускаем
            if (!empty($m['delivery']) || !empty($m['read']))
                continue;
            if (!empty($m['message']['is_echo']))
                continue;

            $sender = $m['sender']['id'] ?? '';
            if ($firstSender === '' && $sender)
                $firstSender = $sender;

            $ts = isset($m['timestamp']) ? date('Y-m-d H:i:s', (int) floor($m['timestamp'] / 1000)) : date('Y-m-d H:i:s');

            // POSTBACK
            if (!empty($m['postback'])) {
                $pb = $m['postback'];
                $title = $pb['title'] ?? '';
                $payloadStr = $pb['payload'] ?? '';
                $mid = 'postback:' . ($m['mid'] ?? ($m['timestamp'] ?? uniqid('pb_', true)));
                $ids[] = $mid;

                $lines = [];
                $lines[] = "📥 Новое событие Messenger (Postback)";
                if ($pageId)
                    $lines[] = "📄 Страница: {$pageId}";
                $lines[] = "👤 От: {$sender}";
                $lines[] = "🕒 Время: {$ts}";
                $lines[] = "🔘 Кнопка: {$title}";
                if ($payloadStr)
                    $lines[] = "📦 Payload: {$payloadStr}";
                $outChunks[] = implode("\n", $lines);
                continue;
            }

            // СООБЩЕНИЕ
            if (!empty($m['message'])) {
                $msg = $m['message'];
                $mid = $msg['mid'] ?? ('msg:' . ($m['timestamp'] ?? uniqid('m_', true)));
                $ids[] = $mid;

                $lines = [];
                $lines[] = "📥 Новое сообщение Messenger";
                if ($pageId)
                    $lines[] = "📄 Страница: {$pageId}";
                $lines[] = "👤 От: {$sender}";
                $lines[] = "🕒 Время: {$ts}";

                if (!empty($msg['text'])) {
                    $lines[] = "💬 Текст:\n" . $msg['text'];
                }
                if (!empty($msg['quick_reply']['payload'])) {
                    $lines[] = "⚡ Quick Reply Payload: " . $msg['quick_reply']['payload'];
                }
                // attachments
                $attachLines = [];
                foreach (($msg['attachments'] ?? []) as $a) {
                    $type = $a['type'] ?? 'file';
                    $payloadA = $a['payload'] ?? [];
                    $url = $payloadA['url'] ?? '';
                    if ($type === 'location' && !empty($payloadA['coordinates'])) {
                        $lat = $payloadA['coordinates']['lat'] ?? '';
                        $lng = $payloadA['coordinates']['long'] ?? ($payloadA['coordinates']['lng'] ?? '');
                        $attachLines[] = "📍 Локация: {$lat}, {$lng}";
                    } else {
                        $attachLines[] = "📎 {$type}" . ($url ? " ({$url})" : "");
                    }
                }
                if ($attachLines)
                    $lines[] = implode("\n", $attachLines);

                $outChunks[] = implode("\n", $lines);
            }
        }
    }

    return [
        'ok' => !empty($ids),
        'platform' => 'messenger',
        'text' => $outChunks ? implode("\n\n" . str_repeat("—", 32) . "\n\n", $outChunks) : "ℹ️ Нет входящих сообщений Messenger.",
        'message_ids' => array_values(array_unique($ids)),
        'sender_id' => $firstSender,
        'sender_name' => $firstName,
    ];
}

/**
 * ----- Instagram -----
 * object: "instagram", entry[*].messaging[*]
 * Теперь выводит: Имя Фамилию (если есть), @username и ссылку на профиль.
 * Числовой sender_id не показываем (строка с ним закомментирована для дебага).
 */
function format_instagram_payload(array $payload): array
{
    $outChunks = [];
    $ids = [];
    $firstSender = '';
    $firstName = '';

    // ---------- Вспомогательные вытягивалки полей ----------
    $getSafe = function ($src, $path, $default = '') {
        $cur = $src;
        foreach ($path as $k) {
            if (!is_array($cur) || !array_key_exists($k, $cur))
                return $default;
            $cur = $cur[$k];
        }
        return $cur;
    };
    $buildDisplay = function (string $nameFull, string $username): string {
        if ($nameFull && $username)
            return "{$nameFull} (@{$username})";
        if ($nameFull)
            return $nameFull;
        if ($username)
            return "@{$username}";
        return "неизвестно";
    };
    $pushAttachmentLines = function (array $attachments) {
        $lines = [];
        foreach ($attachments as $a) {
            $type = $a['type'] ?? 'file';
            $payloadA = $a['payload'] ?? [];
            $url = $payloadA['url'] ?? '';
            if ($type === 'location' && !empty($payloadA['coordinates'])) {
                $lat = $payloadA['coordinates']['lat'] ?? '';
                $lng = $payloadA['coordinates']['long'] ?? ($payloadA['coordinates']['lng'] ?? '');
                $lines[] = "📍 Локация: {$lat}, {$lng}";
            } else {
                $lines[] = "📎 {$type}" . ($url ? " ({$url})" : "");
            }
        }
        return $lines;
    };

    // ---------- ВАРИАНТ 1: entry[].messaging[] ----------
    foreach ($payload['entry'] as $entry) {
        if (!empty($entry['messaging']) && is_array($entry['messaging'])) {
            $igPageId = $entry['id'] ?? '';

            foreach ($entry['messaging'] as $m) {
                // служебные пропускаем
                if (!empty($m['delivery']) || !empty($m['read']))
                    continue;
                if (!empty($m['message']['is_echo']))
                    continue;

                // sender & profile bits — IG обычно НЕ присылает ФИО/username в вебхуке, но если пришло — выведем
                $senderId = $m['sender']['id'] ?? '';
                $nameFull = $m['sender']['name'] ?? $getSafe($m, ['message', 'from', 'name'], '');
                $first = $m['sender']['first_name'] ?? $getSafe($m, ['message', 'from', 'first_name'], '');
                $last = $m['sender']['last_name'] ?? $getSafe($m, ['message', 'from', 'last_name'], '');
                if (!$nameFull)
                    $nameFull = trim($first . ' ' . $last);
                $username = $m['sender']['username'] ?? $getSafe($m, ['message', 'from', 'username'], '');

                if ($firstSender === '' && $senderId)
                    $firstSender = $senderId;
                if ($firstName === '' && ($nameFull || $username))
                    $firstName = $nameFull ?: ('@' . $username);

                $ts = isset($m['timestamp']) ? date('Y-m-d H:i:s', (int) floor($m['timestamp'] / 1000)) : date('Y-m-d H:i:s');

                // POSTBACK
                if (!empty($m['postback'])) {
                    $pb = $m['postback'];
                    $title = $pb['title'] ?? '';
                    $payloadStr = $pb['payload'] ?? '';
                    $mid = 'ig_postback:' . ($m['mid'] ?? ($m['timestamp'] ?? uniqid('igpb_', true)));
                    $ids[] = $mid;

                    $display = $buildDisplay($nameFull, $username);
                    $lines = [];
                    $lines[] = "📥 Новое событие Instagram (Postback)";
                    if ($igPageId)
                        $lines[] = "📸 Аккаунт (ID страницы): {$igPageId}";
                    $lines[] = "👤 От: {$display}";
                    if ($username)
                        $lines[] = "🔗 Аккаунт: @{$username} — https://instagram.com/{$username}";
                    // $lines[] = "🧩 sender_id: {$senderId}"; // DEBUG при необходимости
                    $lines[] = "🕒 Время: {$ts}";
                    $lines[] = "🔘 Кнопка: {$title}";
                    if ($payloadStr)
                        $lines[] = "📦 Payload: {$payloadStr}";
                    $outChunks[] = implode("\n", $lines);
                    continue;
                }

                // MESSAGE
                if (!empty($m['message'])) {
                    $msg = $m['message'];
                    $mid = $msg['mid'] ?? ('ig_msg:' . ($m['timestamp'] ?? uniqid('igm_', true)));
                    $ids[] = $mid;

                    $display = $buildDisplay($nameFull, $username);
                    $lines = [];
                    $lines[] = "📥 Новое сообщение Instagram";
                    if ($igPageId)
                        $lines[] = "📸 Аккаунт (ID страницы): {$igPageId}";
                    $lines[] = "👤 От: {$display}";
                    if ($username)
                        $lines[] = "🔗 Аккаунт: @{$username} — https://instagram.com/{$username}";
                    // $lines[] = "🧩 sender_id: {$senderId}"; // DEBUG
                    $lines[] = "🕒 Время: {$ts}";

                    if (isset($msg['text']) && $msg['text'] !== '') {
                        $lines[] = "💬 Текст:\n" . $msg['text'];
                    } else {
                        $lines[] = "💬 Текст: (пусто)";
                    }

                    // вложения
                    if (!empty($msg['attachments'])) {
                        $attLines = $pushAttachmentLines($msg['attachments']);
                        if ($attLines)
                            $lines[] = implode("\n", $attLines);
                    }

                    $outChunks[] = implode("\n", $lines);
                }
            }
        }
    }

    // ---------- ВАРИАНТ 2: entry[].changes[].value ----------
    foreach ($payload['entry'] as $entry) {
        if (empty($entry['changes']) || !is_array($entry['changes']))
            continue;

        foreach ($entry['changes'] as $chg) {
            $field = $chg['field'] ?? '';
            $val = $chg['value'] ?? [];

            // Ищем массив сообщений в value
            $msgArr = [];
            if (!empty($val['messages']) && is_array($val['messages'])) {
                $msgArr = $val['messages'];
            } elseif (!empty($val['message']) && is_array($val['message'])) {
                $msgArr = [$val['message']];
            } elseif (isset($val['text']) || isset($val['attachments'])) {
                // Иногда само value = одно сообщение
                $msgArr = [$val];
            } else {
                continue;
            }

            foreach ($msgArr as $msg) {
                // Достаём профиль/имя/юзернейм из разных мест, если повезёт
                $senderId = $msg['from']['id'] ?? ($val['from']['id'] ?? '');
                $nameFull = ($msg['from']['name'] ?? '') ?: ($val['from']['name'] ?? '');
                $username = ($msg['from']['username'] ?? '') ?: ($val['from']['username'] ?? '');

                if ($firstSender === '' && $senderId)
                    $firstSender = $senderId;
                if ($firstName === '' && ($nameFull || $username))
                    $firstName = $nameFull ?: ('@' . $username);

                $mid = $msg['id'] ?? $msg['mid'] ?? ('ig_chg:' . uniqid());
                $ids[] = $mid;

                $ts = $msg['timestamp'] ?? $val['timestamp'] ?? $entry['time'] ?? time();
                $tsF = date('Y-m-d H:i:s', is_numeric($ts) ? (int) (strlen((string) $ts) > 10 ? floor($ts / 1000) : $ts) : time());

                $display = $buildDisplay($nameFull, $username);
                $lines = [];
                $lines[] = "📥 Новое сообщение Instagram (changes)";
                $lines[] = "👤 От: {$display}";
                if ($username)
                    $lines[] = "🔗 Аккаунт: @{$username} — https://instagram.com/{$username}";
                // $lines[] = "🧩 sender_id: {$senderId}"; // DEBUG
                $lines[] = "🕒 Время: {$tsF}";

                if (isset($msg['text']) && $msg['text'] !== '') {
                    $lines[] = "💬 Текст:\n" . $msg['text'];
                } elseif (isset($val['text']) && $val['text'] !== '') {
                    $lines[] = "💬 Текст:\n" . $val['text'];
                } else {
                    $lines[] = "💬 Текст: (пусто)";
                }

                if (!empty($msg['attachments'])) {
                    $attLines = $pushAttachmentLines($msg['attachments']);
                    if ($attLines)
                        $lines[] = implode("\n", $attLines);
                } elseif (!empty($val['attachments'])) {
                    $attLines = $pushAttachmentLines($val['attachments']);
                    if ($attLines)
                        $lines[] = implode("\n", $attLines);
                }

                $outChunks[] = implode("\n", $lines);
            }
        }
    }

    $text = $outChunks ? implode("\n\n" . str_repeat("—", 32) . "\n\n", $outChunks) : "ℹ️ Нет входящих сообщений Instagram.";
    return [
        'ok' => !empty($ids),
        'platform' => 'instagram',
        'text' => $text,
        'message_ids' => array_values(array_unique($ids)),
        'sender_id' => $firstSender,
        'sender_name' => $firstName,
    ];
}

/**
 * ----- ОТПРАВКА В WHATSAPP -----
 * 1) send_whatsapp_text_detailed — текст (режет на части).
 * 2) send_whatsapp_template — шаблон (открывает 24ч окно).
 * 3) forward_with_fallback — если 131047, шлём шаблон и повторяем текст.
 */
function send_whatsapp_template(
    string $graphVersion,
    string $phoneNumberId,
    string $accessToken,
    string $toPhone,
    string $templateName,
    string $languageCode = 'en_US',
    array $components = []
): array {
    $endpoint = "https://graph.facebook.com/{$graphVersion}/" . rawurlencode($phoneNumberId) . "/messages";
    $to = bu_msisdn($toPhone);
    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $to,
        'type' => 'template',
        'template' => [
            'name' => $templateName,
            'language' => ['code' => $languageCode],
        ],
    ];
    if ($components)
        $payload['template']['components'] = $components;

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json; charset=utf-8',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $decoded = json_decode((string) $resp, true);

    wa_log_info('Template Send:', [
        'action' => 'send_template',
        'to' => $to,
        'template' => $templateName,
        'http_code' => $code,
        'resp' => $decoded ?: $resp,
        'curl_err' => $err ?: null,
    ]);

    $firstError = $decoded['error']['code'] ?? ($decoded['errors'][0]['code'] ?? null);

    return [
        'ok' => ($code >= 200 && $code < 300),
        'http_code' => $code,
        'resp' => $decoded ?: $resp,
        'first_error_code' => $firstError,
    ];
}



function send_whatsapp_text_detailed(
    string $graphVersion,
    string $phoneNumberId,
    string $accessToken,
    string $toPhone,
    string $text,
    ?string $contextUserId = null,
    bool $bossAnswer = false,
): array {
    $endpoint = "https://graph.facebook.com/{$graphVersion}/" . rawurlencode($phoneNumberId) . "/messages";
    $to = bu_msisdn($toPhone);

    // --- Ютилиты для работы с UTF-8 без порчи символов ---
    $strlen = function (string $s): int {
        return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
    };
    $substr = function (string $s, int $start, ?int $len = null): string {
        if (function_exists('mb_substr')) {
            return $len === null ? mb_substr($s, $start, null, 'UTF-8') : mb_substr($s, $start, $len, 'UTF-8');
        }
        return $len === null ? substr($s, $start) : substr($s, $start, $len);
    };

    $L = $strlen($text);

    // --- Сформируем части с пометкой, где будут кнопки ---
    // Каждая часть: ['content' => string, 'interactive' => bool]
    $parts = [];

    if ($L <= 1000) {
        // одна часть с кнопками
        $parts[] = ['content' => $text, 'interactive' => true];
    } elseif ($L <= 2000) {
        // делим на две части пополам; последняя с кнопками
        $p1 = intdiv($L, 2);
        $part1 = $substr($text, 0, $p1);
        $part2 = $substr($text, $p1);
        $parts[] = ['content' => $part1, 'interactive' => false];
        $parts[] = ['content' => $part2, 'interactive' => true];
    } elseif ($L <= 5000) {
        // первая = L - 1000 (текст), последняя = 1000 (с кнопками)
        $keepForLast = 1000;
        $firstLen = $L - $keepForLast; // 1001..4000
        $part1 = $substr($text, 0, $firstLen);
        $part2 = $substr($text, $firstLen);
        $parts[] = ['content' => $part1, 'interactive' => false];
        $parts[] = ['content' => $part2, 'interactive' => true];
    } else {
        // > 5000: первые части — ровные, последняя 1000 (с кнопками),
        // при этом размер любой "ровной" части ≤ 4000.
        $lastLen = 1000;
        $earlyTotal = $L - $lastLen;

        // Сколько ровных кусков нужно, чтобы каждый был ≤ 4000
        $k = (int) ceil($earlyTotal / 4000); // k >= 2
        $chunkSize = (int) ceil($earlyTotal / $k); // гарантированно ≤ 4000

        $offset = 0;
        for ($i = 0; $i < $k; $i++) {
            // для последних ранних частей подбираем точную длину
            $remaining = $earlyTotal - $offset;
            $take = ($i === $k - 1) ? $remaining : min($chunkSize, $remaining);
            $parts[] = ['content' => $substr($text, $offset, $take), 'interactive' => false];
            $offset += $take;
        }
        // последняя 1000 с кнопками
        $parts[] = ['content' => $substr($text, $earlyTotal), 'interactive' => true];
    }

    $last = ['ok' => true, 'http_code' => 200, 'resp' => null, 'first_error_code' => null];

    foreach ($parts as $idx => $p) {
        // Собираем базовое тело
        $body = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $p['content'], 'preview_url' => false],
        ];

        /*// Если интерактив — прикрутить кнопки из глобалок
        if (!empty($p['interactive'])) {
            // ВАЖНО: wa_attach_buttons_from_globals возвращает новый body
            $body = wa_attach_buttons($body);
        }*/

        if (!empty($p['interactive']) && !$bossAnswer) {
            // укажите ваш бизнес-номер (E.164 без +) в конфиге:
            // $GLOBALS['WA_BUSINESS_E164'] = '4915212397076';
            $body = wa_attach_number_links($body, $GLOBALS['WA_BUSINESS_NUMBER'], 'de', $contextUserId);
        }

        // Отправка
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json; charset=utf-8',
            ],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        $decoded = json_decode((string) $resp, true);
        $firstError = $decoded['error']['code'] ?? ($decoded['errors'][0]['code'] ?? null);

        wa_log_info('WhatsApp Message Send:', [
            'action' => (!empty($p['interactive']) ? 'send_interactive' : 'send_text'),
            'to' => $to,
            'part' => ($idx + 1) . '/' . count($parts),
            'http_code' => $code,
            'resp' => $decoded ?: $resp,
            'curl_err' => $err ?: null,
        ]);

        $ok = ($code >= 200 && $code < 300);
        $last = ['ok' => $ok, 'http_code' => $code, 'resp' => $decoded ?: $resp, 'first_error_code' => $firstError];
        if (!$ok)
            return $last; // стоп на первой ошибке
    }

    return $last;
}


/**
 * Попытка отправки текста; при 131047 — отправляем шаблон (hello_world) и повторяем.
 */
function forward_with_fallback(
    string $graphVersion,
    string $phoneNumberId,
    string $accessToken,
    string $toPhone,
    string $text,
    string $fallbackTemplate = 'hello_world',
    string $fallbackLang = 'en_US',
    ?string $contextUserId = null,   
    bool $bossAnswer = false,    // <-- новый параметр
): bool {
    $res = send_whatsapp_text_detailed($graphVersion, $phoneNumberId, $accessToken, $toPhone, $text, $contextUserId, $bossAnswer);
    if ($res['ok'])
        return true;

    if ((int) ($res['first_error_code'] ?? 0) === 131047) {
        $t = send_whatsapp_template($graphVersion, $phoneNumberId, $accessToken, $toPhone, $fallbackTemplate, $fallbackLang, $bossAnswer);
        if (!$t['ok'])
            return false;
        $res2 = send_whatsapp_text_detailed($graphVersion, $phoneNumberId, $accessToken, $toPhone, $text, $contextUserId, $bossAnswer);
        return $res2['ok'];
    }
    return false;
}


/**
 * Прикрепляет к TEXT-сообщению интерактивные кнопки из глобалок:
 *  - $GLOBALS['MESSAGE_BUTTONS_USED']  — какие кнопки показывать (порядок, макс. 10)
 *  - $GLOBALS['MESSAGE_BUTTONS']       — тексты/эмодзи + флаг 'text_answer_possible'
 * Подписи берутся ТОЛЬКО через msg_btn().
 *
 * ≤3 кнопок → быстрые ответы (interactive/button), 4–10 → меню-список (interactive/list).
 * Кнопки, у которых 'text_answer_possible' = true, получают id вида "<key>.text".
 *
 * @param array $body Обычное тело сообщения WhatsApp (type='text' с ['text']['body']).
 * @return array Обновлённое тело (type='interactive') либо исходное, если кнопок нет.
 */
/*
function wa_attach_buttons(array $body): array
{
    $usedKeys = $GLOBALS['MESSAGE_BUTTONS_USED'] ?? [];
    $map = $GLOBALS['MESSAGE_BUTTONS'] ?? [];

    // 1) Оставим только определённые в локализации и ограничим до 10
    $keys = [];
    foreach ($usedKeys as $k) {
        if (isset($map[$k]))
            $keys[] = (string) $k;
        if (count($keys) >= 10)
            break;
    }
    if (!$keys)
        return $body;

    // 2) Исходный текст (лимит интерактивов ~1024 символа)
    $text = '';
    if (($body['type'] ?? '') === 'text' && isset($body['text']['body'])) {
        $text = (string) $body['text']['body'];
    } elseif (isset($body['interactive']['body']['text'])) {
        $text = (string) $body['interactive']['body']['text'];
    }
    $truncate = static function (string $s, int $max): string {
        if ($max <= 0)
            return $s;
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return (mb_strlen($s, 'UTF-8') > $max)
                ? rtrim(mb_substr($s, 0, max(1, $max - 1), 'UTF-8')) . '…'
                : $s;
        }
        return (strlen($s) > $max) ? rtrim(substr($s, 0, max(1, $max - 1))) . '…' : $s;
    };
    $text = $truncate($text, 1024);

    // 3) Разрешённые заголовки (ТОЛЬКО через msg_btn) и ID с флагом ".text" при необходимости
    $resolved = [];
    foreach ($keys as $k) {
        $textAnswer = !empty($map[$k]['text_answer_possible']); // true => ожидается ввод текста
        $resolved[] = [
            'id' => $textAnswer ? ($k . '.text') : $k,
            'title' => (string) msg_btn($k), // подпись только из вашей локализации
        ];
    }

    // 4) Соберём interactive
    unset($body['text']);
    $body['type'] = 'interactive';

    if (count($resolved) <= 3) {
        // ----- Быстрые ответы (reply buttons): title ≤ 20
        $btns = [];
        foreach ($resolved as $b) {
            $btns[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => $b['id'],
                    'title' => $truncate($b['title'], 20),
                ],
            ];
        }
        $body['interactive'] = [
            'type' => 'button',
            'body' => ['text' => $text],
            'action' => ['buttons' => $btns],
        ];
    } else {
        // ----- Меню-список (4–10): button ≤ 20, section title ≤ 24, row title ≤ 24
        // Кнопка-открывашка и заголовок секции — берем из уже разрешённых заголовков,
        // чтобы не добавлять новой локализации.
        $openBtnLabel = $truncate($resolved[0]['title'], 20);
        $sectionTitle = $truncate(($resolved[1]['title'] ?? $resolved[0]['title']), 24);

        $rows = [];
        foreach ($resolved as $b) {
            $rows[] = [
                'id' => $b['id'],
                'title' => $truncate($b['title'], 24),
            ];
        }

        $body['interactive'] = [
            'type' => 'list',
            'body' => ['text' => $text],
            'action' => [
                'button' => $openBtnLabel,
                'sections' => [
                    [
                        'title' => $sectionTitle,
                        'rows' => $rows,
                    ]
                ],
            ],
        ];
    }

    return $body;
}


/**
 * Встраивает «меню из ссылок» (без interactive).
 * Для пунктов с 'text_answer_possible' = true добавляет в префилл: " // " после номера.
 * Формат префилла:
 *   "<emoji> <CODE> <N> "           — обычные пункты
 *   "<emoji> <CODE> <N> // "        — пункты, где ожидается текст
 *
 * @param array       $body         Тело WA-сообщения (type='text' с ['text']['body']).
 * @param string|null $businessE164 Ваш бизнес-номер (E.164 без +). Если null — берём из глобалок.
 * @param string      $lang         Язык для msg_btn().
 * @return array
 */
function wa_attach_number_links(
    array $body,
    ?string $businessE164 = null,
    string $lang = 'de',
    ?string $originalUserId = null
): array {




    $map = $GLOBALS['MESSAGE_BUTTONS'] ?? [];
    $used = $GLOBALS['MESSAGE_BUTTONS_USED'] ?? [];

    // Куда открывать чат
    $dest = preg_replace('~\D+~', '', (string) ($businessE164 ?? ($GLOBALS['WA_BUSINESS_NUMBER'] ?? '')));
    if ($dest === '')
        return $body;

    // Текст исходника
    $text = '';
    if (($body['type'] ?? '') === 'text' && isset($body['text']['body'])) {
        $text = (string) $body['text']['body'];
    } elseif (isset($body['interactive']['body']['text'])) {
        $text = (string) $body['interactive']['body']['text'];
    }

    // Берём до 9 элементов по порядку из USED и только те, что есть в локализации
    $keys = [];
    foreach ($used as $k) {
        if (isset($map[$k]))
            $keys[] = (string) $k;
        if (count($keys) >= 9)
            break;
    }
    if (!$keys)
        return $body;

    $recipientWaId = preg_replace('~\D+~', '', (string) $originalUserId);

    // Короткий код меню (8 hex)
    $code = wa_menu_code_reserve([
        'platform' => 'whatsapp',
        'user_id' => $recipientWaId ?: null,                 // <-- ВАЖНО
        'to_id' => $GLOBALS['PHONE_NUMBER_ID'] ?? null,    // ваш phone_number_id
        'direction' => 'out',
        'type' => 'text',
        'text_body' => $text,
        'payload_json' => ['menu_keys' => $keys],
        'msg_ts' => time(),
    ]);
    wa_log_info('Reserved WA menu code', ['code' => $code, 'user_id' => $recipientWaId]);
    wa_log_info('Reserved WA menu code', ['code' => $code, 'to' => $dest, 'text' => $text]);

    $lines = [];
    foreach ($keys as $i => $k) {
        $num = (string) ($i + 1);
        $emoji = (string) ($map[$k]['emoji'] ?? '');
        $label = msg_btn($k, $lang, 0, true); // только ваша локализация
        $needText = !empty($map[$k]['text_answer_possible']);

        // Префилл: "<emoji> <CODE> <N> " или "<emoji> <CODE> <N> // "
        $prefill = trim($emoji . ' ' . $code . ' ' . $num) . ($needText ? ' Text: ' : ' ');
        $link = 'https://wa.me/' . $dest . '?text=' . rawurlencode($prefill);

        $lines[] = $num . ') ' . $label;
        $lines[] = $link;
    }

    $menuBlock = implode("\n", $lines);

    // Обратно — обычный TEXT (не interactive)
    $body['type'] = 'text';
    $body['text'] = [
        'body' => rtrim($text . "\n\n" . $menuBlock),
        'preview_url' => false,
    ];
    unset($body['interactive']);

    return $body;
}


/**
 * Простой обработчик выбранной команды.
 * Для примера реализован №3 (answer): отправляет клиенту объединённое сообщение
 *   «их сообщение + ваш ответ».
 *
 * @param array $cmd Результат wa_bot_handle_own_message_code()
 * @return array ['ok'=>bool, 'sent'=>bool, 'http_code'=>int|null, 'error'=>string|null]
 */
function wa_bot_process_own_command(array $cmd): array
{
    $fn = __FUNCTION__;
    wa_log_enter($fn, ['code' => $cmd['code'] ?? '', 'num' => $cmd['command_num'] ?? 0, 'key' => $cmd['command_key'] ?? '']);

    $res = ['ok' => false, 'sent' => false, 'http_code' => null, 'error' => null];

    if (empty($cmd['ok'])) {
        $res['error'] = 'invalid command payload';
        wa_log_return($fn, $res);
        return $res;
    }

    $key = (string) ($cmd['command_key'] ?? '');
    $num = (int) ($cmd['command_num'] ?? 0);

    // Проверим, куда слать
    $toE164 = (string) ($cmd['target_user_id'] ?? '');
    if ($toE164 === '' && !empty($cmd['original']['user_id'])) {
        $toE164 = (string) $cmd['original']['user_id'];
    }
    if ($toE164 === '') {
        $res['error'] = 'no target user_id';
        wa_log_return($fn, $res);
        return $res;
    }

    $ourText = (string) ($cmd['user_text'] ?? '');

    // Карта действий (при желании развить логику для прочих команд)
    switch ($key) {
        case 'answer': // №3 – просто ответить текстом
            if ($ourText === '') {
                $res['error'] = 'no reply text';
                wa_log_return($fn, $res);
                return $res;
            }

            $send = send_whatsapp_text_detailed(
                (string) ($GLOBALS['GRAPH_VERSION'] ?? 'v20.0'),
                (string) ($GLOBALS['PHONE_NUMBER_ID'] ?? ''),
                (string) ($GLOBALS['ACCESS_TOKEN'] ?? ''),
                $toE164,
                $ourText,
                null,
                true
            );

            $res['ok'] = (bool) ($send['ok'] ?? false);
            $res['sent'] = $res['ok'];
            $res['http_code'] = $send['http_code'] ?? null;
            $res['error'] = $res['ok'] ? null : ('send failed: ' . json_encode($send, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            wa_log_return($fn, $res);
            return $res;

        case 'translate':
        case 'translate_simplify':
        case 'answer_chatgpt':
        case 'answer_chatgpt_automatic':
            // TODO: реализовать нужную вам логику для остальных команд
            $res['ok'] = true;
            $res['error'] = 'not_implemented_for_key_' . $key;
            wa_log_return($fn, $res);
            return $res;

        default:
            $res['error'] = 'unknown_command_key';
            wa_log_return($fn, $res);
            return $res;
    }
}