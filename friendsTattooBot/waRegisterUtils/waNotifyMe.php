<?php declare(strict_types=1);
// wa_notify_me.php
// Открой в браузере: https://yourdomain/wa_notify_me.php
// Опционально: https://yourdomain/wa_notify_me.php?to=+4917632565824&text=Привет!
// Если text не задан, отправим шаблон hello_world (дойдёт всегда)

//создает новый чат с номером и отправляет туда сообщение или шаблон

require_once __DIR__ . 'waConfig.php';
declare(strict_types=1);

function onlyDigits(string $s): string { return preg_replace('/\D+/', '', $s); }

function wa_call(string $graphVersion, string $path, string $token, array $payload): array {
    $url = "https://graph.facebook.com/{$graphVersion}/{$path}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT        => 30,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    $json = json_decode($resp, true);
    return [$code, $err ?: null, $json ?? $resp];
}

$toRaw = $_GET['to']   ?? $DEFAULT_TO;
$to    = onlyDigits($toRaw);                  // E.164 без плюса
$text  = isset($_GET['text']) ? trim($_GET['text']) : '';

// 1) Если есть text — попробуем отправить обычное сообщение (работает при открытом 24ч окне)
$resultText = null;
if ($text !== '') {
    $payload = [
        'messaging_product' => 'whatsapp',
        'to'   => $to,
        'type' => 'text',
        'text' => ['body' => $text, 'preview_url' => false],
    ];
    $path = "{$PHONE_NUMBER_ID}/messages";
    $resultText = wa_call($GRAPH_VERSION, $path, $ACCESS_TOKEN, $payload);
}

// 2) Если text не задан ИЛИ текстовая отправка не прошла (например, нет 24ч окна) — шлём шаблон hello_world
$resultTpl = null;
if ($text === '' || ($resultText && $resultText[0] >= 400)) {
    $payload = [
        'messaging_product' => 'whatsapp',
        'to'   => $to,
        'type' => 'template',
        'template' => [
            'name'     => $TEMPLATE_NAME,
            'language' => ['code' => $TEMPLATE_LANG],
        ],
    ];
    $path = "{$PHONE_NUMBER_ID}/messages";
    $resultTpl = wa_call($GRAPH_VERSION, $path, $ACCESS_TOKEN, $payload);
}

// Вывод
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'request' => [
        'to_e164'   => '+' . $to,
        'text_used' => $text !== '',
        'template'  => $TEMPLATE_NAME . ' (' . $TEMPLATE_LANG . ')',
        'endpoint'  => "https://graph.facebook.com/{$GRAPH_VERSION}/{$PHONE_NUMBER_ID}/messages",
    ],
    'send_text_attempt' => $resultText ? [
        'http_code'  => $resultText[0],
        'curl_error' => $resultText[1],
        'response'   => $resultText[2],
    ] : null,
    'send_template_attempt' => $resultTpl ? [
        'http_code'  => $resultTpl[0],
        'curl_error' => $resultTpl[1],
        'response'   => $resultTpl[2],
    ] : null,
], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
