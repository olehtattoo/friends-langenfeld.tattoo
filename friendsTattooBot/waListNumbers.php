<?php
// wa_list_numbers.php
// Открываешь: https://yourdomain/wa_list_numbers.php
// ВНИМАНИЕ: в коде зашит боевой токен. После проверки лучше перевыпусти токен и вынеси его в env/конфиг.

declare(strict_types=1);

// --- НАСТРОЙКИ ---
$GRAPH_VERSION = 'v21.0';
$WABA_ID       = '1852405798675477'; // ВАЖНО: в КАВЫЧКАХ, не в бэктиках
$ACCESS_TOKEN  = 'EAAP96vFSjHoBPHJIoDaFwDCazs4pC8bVo4e9j2ZBY8GZBp6rDJ0sGscjZCD97tHVS5fGy4pnzu7ZBWZAxcDNu60jivDfhOX8YSGLBCNwIMu295sEL1aNZAHzAh50fq6KyPwZAUZBf3sVH9ZBMXSWTBMO4UW7Yfr4szPqLF5pccChQIoo9goPZAzkuf38xcxU8GzwZDZD';
// ---------------

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/wa_list_numbers_error.log');

function fb_get(string $url, string $token): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            'User-Agent: WABA-Checker/1.0'
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    $json = null;
    if (is_string($body)) {
        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $json = $decoded;
        }
    }
    return [
        'http_code' => $code,
        'curl_error' => $err ?: null,
        'raw_body' => $json === null ? $body : null,
        'json' => $json,
    ];
}

$fields = http_build_query([
    'fields' => 'id,display_phone_number,verified_name,name_status',
]);

$url = "https://graph.facebook.com/{$GRAPH_VERSION}/{$WABA_ID}/phone_numbers?{$fields}";

$result = fb_get($url, $ACCESS_TOKEN);

// Формируем понятный вывод
$output = [
    'request' => [
        'url' => $url,              // токен НЕ выводим
        'graph_version' => $GRAPH_VERSION,
        'waba_id' => $WABA_ID,
    ],
    'response' => [
        'http_code' => $result['http_code'],
        'curl_error' => $result['curl_error'],
        'body' => $result['json'] ?? $result['raw_body'],
    ],
];

header('Content-Type: application/json; charset=utf-8');
echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
