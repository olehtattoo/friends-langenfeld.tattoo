<?php
// Настройки

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); // например: /friends-langenfeld.tattoo
$domain = $protocol . $host . $path;

/* $domain = 'https://friends-langenfeld.tattoo'; */
$uploadFolder = '/userUploads/';
$uploadDir = __DIR__ . $uploadFolder;
$publicUrl = $domain . $uploadFolder;
$shortFile = __DIR__ . $uploadFolder . 'json/shortlinks.json'; // Для хранения коротких ссылок

if (!isset($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file']);
    exit;
}

$file = $_FILES['image'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Upload error']);
    exit;
}

// Проверка типа файла
$allowed = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($file['type'], $allowed)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type']);
    exit;
}

// Генерация уникального имени
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$basename = bin2hex(random_bytes(5));
$filename = $basename . '.jpg';
$filepath = $uploadDir . $filename;

// Сжатие и конвертация (JPEG, качество 70)
$img = null;
if ($file['type'] === 'image/png') {
    $img = imagecreatefrompng($file['tmp_name']);
} elseif ($file['type'] === 'image/jpeg') {
    $img = imagecreatefromjpeg($file['tmp_name']);
} elseif ($file['type'] === 'image/webp') {
    $img = imagecreatefromwebp($file['tmp_name']);
}
if (!$img) {
    http_response_code(400);
    echo json_encode(['error' => 'Image error']);
    exit;
}
imagejpeg($img, $filepath, 70);
imagedestroy($img);

// Генерация короткой ссылки
$short = substr(bin2hex(random_bytes(3)), 0, 6);

// Сохраняем короткую ссылку
$links = [];
if (file_exists($shortFile)) {
    $links = json_decode(file_get_contents($shortFile), true) ?: [];
}
$links[$short] = $filename;
file_put_contents($shortFile, json_encode($links));

// Ответ
echo json_encode([
    'url' => $publicUrl . $filename, // Прямая ссылка на изображение
    'short' => $publicUrl . $filename // Тоже прямая ссылка (или оставьте короткую, если нужен редирект)
]);