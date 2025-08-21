<?php
// filepath: c:\Users\user\Documents\www\friends-langenfeld.tattoo\cron_cleanup_uploads.php

$uploadDir = __DIR__ . '/uploads/';
$shortFile = __DIR__ . '/shortlinks.json';
$maxSize = 20 * 1024 * 1024 * 1024; // 20 GB
// $maxSize = 1;   //for testing, set to 1 byte to ensure cleanup runs

// Получить все файлы (без папок)
$files = array_filter(
    glob($uploadDir . '*'),
    function ($file) {
        return is_file($file);
    }
);

// Получить массив: файл => время изменения
$fileTimes = [];
$totalSize = 0;
foreach ($files as $file) {
    $fileTimes[$file] = filemtime($file);
    $totalSize += filesize($file);
}

// Загрузить shortlinks.json
$links = [];
if (file_exists($shortFile)) {
    $links = json_decode(file_get_contents($shortFile), true) ?: [];
}

// Если превышен лимит, удалять старые файлы и их записи из shortlinks.json
if ($totalSize > $maxSize) {
    // Сортировать по времени (старые первыми)
    asort($fileTimes);
    foreach ($fileTimes as $file => $time) {
        $basename = basename($file);
        // Удалить файл
        if (unlink($file)) {
            $totalSize -= filesize($file);
            // Удалить из shortlinks.json
            foreach ($links as $short => $fname) {
                if ($fname === $basename) {
                    unset($links[$short]);
                }
            }
        }
        if ($totalSize <= $maxSize) {
            break;
        }
    }
    // Сохранить обновленный shortlinks.json
    file_put_contents($shortFile, json_encode($links));
}