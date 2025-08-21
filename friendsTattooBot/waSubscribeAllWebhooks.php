<?php declare(strict_types=1);
// wa_subscribe_all_webhooks.php
// Открой в браузере: https://yourdomain/wa_subscribe_all_webhooks.php
// 1) Получает APP_TOKEN через OAuth (client_credentials) из APP_ID + APP_SECRET
// 2) POST /{APP_ID}/subscriptions — подписывает приложение на все поля WABA
// 3) POST /{WABA_ID}/subscribed_apps — подписывает сам WABA на приложение
// 4) Проверяет статусы подписок
// 5) Показывает APP_TOKEN в выводе (не логируйте его в проде)

require_once __DIR__ . 'waConfig.php';
declare(strict_types=1);



ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/wa_subscribe_webhooks_error.log');

function curl_call(string $url, string $method = 'GET', ?array $json = null, ?string $bearer = null, array $extraHeaders = []): array {
    $ch = curl_init($url);
    $headers = array_merge(['Accept: application/json'], $extraHeaders);
    if ($bearer) $headers[] = 'Authorization: Bearer ' . $bearer;

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 40,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => $method,
    ];
    if ($json !== null) {
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_HTTPHEADER] = $headers;
        $opts[CURLOPT_POSTFIELDS] = json_encode($json, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    $decoded = null;
    if (is_string($body)) {
        $tmp = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) $decoded = $tmp;
    }
    return ['http_code'=>$code,'error'=>$err?:null,'json'=>$decoded,'raw'=>$decoded?null:$body];
}

function get_app_token_via_oauth(string $graphVersion, string $appId, string $appSecret): array {
    $qs = http_build_query([
        'client_id'     => $appId,
        'client_secret' => $appSecret,
        'grant_type'    => 'client_credentials',
    ]);
    $url = "https://graph.facebook.com/{$graphVersion}/oauth/access_token?{$qs}";
    $res = curl_call($url, 'GET');
    $token = $res['json']['access_token'] ?? null;
    $source = 'oauth';
    // Фоллбэк: склейка APP_ID|APP_SECRET (допустимо для /{APP_ID}/subscriptions)
    if (!$token) {
        $token  = $appId . '|' . $appSecret;
        $source = 'fallback_concat';
    }
    return [$token, $source, $res];
}

$results = [];

// 0) Получаем APP_TOKEN
list($APP_TOKEN, $APP_TOKEN_SOURCE, $oauth_raw) = get_app_token_via_oauth($GRAPH_VERSION, $APP_ID, $APP_SECRET);
$results['app_token_fetch'] = [
    'source'      => $APP_TOKEN_SOURCE,   // 'oauth' или 'fallback_concat'
    'http_code'   => $oauth_raw['http_code'],
    'error'       => $oauth_raw['error'],
    'raw_response'=> $oauth_raw['json'] ?? $oauth_raw['raw'],
];

// 1) Подписываем приложение на поля WABA (App token)
$subUrl = "https://graph.facebook.com/{$GRAPH_VERSION}/{$APP_ID}/subscriptions";
$payload = [
    'object'       => 'whatsapp_business_account',
    'callback_url' => $CALLBACK_URL,
    'verify_token' => $VERIFY_TOKEN,
    'fields'       => implode(',', $WEBHOOK_FIELDS),
];
$results['subscribe_app'] = curl_call($subUrl, 'POST', $payload, $APP_TOKEN);

// 2) Подписываем сам WABA на приложение (System User token)
$wabaSubUrl = "https://graph.facebook.com/{$GRAPH_VERSION}/{$WABA_ID}/subscribed_apps";
$results['subscribe_waba'] = curl_call($wabaSubUrl, 'POST', [], $SYSTEM_USER_TOKEN);

// 3) Проверяем, что подписки активны
$checkAppUrl  = "https://graph.facebook.com/{$GRAPH_VERSION}/{$APP_ID}/subscriptions"; // без object=
$results['check_app_subscriptions'] = curl_call($checkAppUrl, 'GET', null, $APP_TOKEN);

$checkWabaUrl = "https://graph.facebook.com/{$GRAPH_VERSION}/{$WABA_ID}/subscribed_apps";
$results['check_waba_subscriptions'] = curl_call($checkWabaUrl, 'GET', null, $SYSTEM_USER_TOKEN);

// 4) Вывод
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'request' => [
    'graph_version'  => $GRAPH_VERSION,
    'app_id'         => $APP_ID,
    'waba_id'        => $WABA_ID,
    'callback_url'   => $CALLBACK_URL,
    'verify_token'   => $VERIFY_TOKEN,
    'fields'         => $WEBHOOK_FIELDS,
  ],
  'derived_app_token' => $APP_TOKEN,  // <-- как просили: показываем APP_TOKEN
  'results' => $results,
], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
