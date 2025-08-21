<?php
// wa_register_number.php
// Открой: https://yourdomain/wa_register_number.php
// Делает:
// 1) POST /{PHONE_NUMBER_ID}/register с PIN
// 2) GET  /{WABA_ID}/phone_numbers — статус номера

require_once __DIR__ . 'waConfig.php';
declare(strict_types=1);

ini_set('display_errors','0');
ini_set('log_errors','1');
ini_set('error_log', __DIR__.'/wa_register_error.log');

function fb_call(string $method, string $url, string $token, ?array $jsonBody=null): array {
    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer '.$token,
        'Accept: application/json',
        'User-Agent: WABA-Register/1.0'
    ];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 40,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
    ];
    if ($jsonBody !== null) {
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_HTTPHEADER] = $headers;
        $opts[CURLOPT_POSTFIELDS] = json_encode($jsonBody, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
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
    return ['http_code'=>$code,'curl_error'=>$err?:null,'raw_body'=>$decoded?null:$body,'json'=>$decoded];
}

// 0) sanity-check — наш токен видит этот WABA?
$checkUrl = "https://graph.facebook.com/{$GRAPH_VERSION}/{$WABA_ID}/phone_numbers?fields=id,display_phone_number,verified_name,platform_type";
$check = fb_call('GET', $checkUrl, $ACCESS_TOKEN);

// 1) Регистрация номера (если видим WABA)
$register = ['http_code'=>0,'curl_error'=>null,'json'=>null,'raw_body'=>null];
if ($check['http_code'] === 200) {
    $registerUrl = "https://graph.facebook.com/{$GRAPH_VERSION}/{$PHONE_NUMBER_ID}/register";
    $payload = ['messaging_product'=>'whatsapp','pin'=>$PIN];
    if ($CERTIFICATE_B64 !== '') $payload['certificate'] = $CERTIFICATE_B64;
    $register = fb_call('POST', $registerUrl, $ACCESS_TOKEN, $payload);
}

// 2) Повторно читаем статус
$statusFields = http_build_query([
    'fields'=>'id,display_phone_number,verified_name,code_verification_status,quality_rating,platform_type'
]);
$statusUrl = "https://graph.facebook.com/{$GRAPH_VERSION}/{$WABA_ID}/phone_numbers?{$statusFields}";
$status = fb_call('GET', $statusUrl, $ACCESS_TOKEN);

// Вывод
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'precheck' => [
      'url'       => $checkUrl,
      'http_code' => $check['http_code'],
      'error'     => $check['curl_error'],
      'body'      => $check['json'] ?? $check['raw_body'],
  ],
  'register_response' => [
      'http_code' => $register['http_code'],
      'error'     => $register['curl_error'],
      'body'      => $register['json'] ?? $register['raw_body'],
  ],
  'status_after' => [
      'url'       => $statusUrl,
      'http_code' => $status['http_code'],
      'error'     => $status['curl_error'],
      'body'      => $status['json'] ?? $status['raw_body'],
  ]
], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
