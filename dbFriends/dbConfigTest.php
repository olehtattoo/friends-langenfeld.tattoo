<?php
/**
 * dbConfigTest.php
 *
 * Тестирует подключение к БД с помощью настроек из dbConfig.php / db_config.php:
 *  1) Подключает конфиг.
 *  2) Открывает PDO-соединение.
 *  3) Создаёт тестовую таблицу (если нет) и вставляет случайную запись.
 *  4) Выводит список всех таблиц и все записи из них (постранично, без лимита).
 *
 * ВАЖНО: удалите этот файл после проверки (он показывает структуру и данные БД).
 */

header('Content-Type: text/plain; charset=utf-8');

// ---------- 1) Подключаем конфиг ----------
$cfgIncluded = false;
$cfgErrors = [];

foreach (['dbConfig.php', 'db_config.php'] as $cfgFile) {
    if (is_file(__DIR__ . DIRECTORY_SEPARATOR . $cfgFile)) {
        require_once __DIR__ . DIRECTORY_SEPARATOR . $cfgFile;
        $cfgIncluded = true;
        break;
    } else {
        $cfgErrors[] = "Не найден файл конфигурации: {$cfgFile}";
    }
}

if (!$cfgIncluded) {
    http_response_code(500);
    echo "ОШИБКА: Не удалось подключить конфиг.\n";
    echo implode("\n", $cfgErrors), "\n";
    exit(1);
}

// Ожидаем, что конфиг задаёт как минимум: $DB_HOST, $DB_PORT, $DB_USER, $DB_PASS, $DB_NAME
$DB_HOST = $DB_HOST ?? null;
$DB_PORT = $DB_PORT ?? 3306;
$DB_USER = $DB_USER ?? null;
$DB_PASS = $DB_PASS ?? '';
$DB_NAME = $DB_NAME ?? ($DB_DATABASE ?? null);

// Сформируем DSN либо используем $DB_DSN из конфига
if (!empty($DB_DSN)) {
    $dsn = $DB_DSN;
} else {
    if (!$DB_HOST || !$DB_USER || !$DB_NAME) {
        http_response_code(500);
        echo "ОШИБКА: В конфиге не хватает обязательных переменных. Нужны: \$DB_HOST, \$DB_USER, \$DB_NAME.\n";
        exit(1);
    }
    $dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";
}

// ---------- 2) Открываем соединение через PDO ----------
try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo "ОШИБКА ПОДКЛЮЧЕНИЯ К БД:\n", $e->getMessage(), "\n";
    exit(1);
}

echo "✅ Соединение установлено.\n";
echo "Хост: {$DB_HOST}\nПорт: {$DB_PORT}\nБД: {$DB_NAME}\nПользователь: {$DB_USER}\n\n";

// ---------- 3) Создаём тестовую таблицу и вставляем запись ----------
$testTable = 'zTestConfig';
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `{$testTable}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `created_at` DATETIME NOT NULL,
            `rnd` VARCHAR(64) NOT NULL,
            `ip` VARCHAR(45) DEFAULT NULL,
            `ua` VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $rnd = bin2hex(random_bytes(8));
    $ip  = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua  = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $stmt = $pdo->prepare("INSERT INTO `{$testTable}` (`created_at`,`rnd`,`ip`,`ua`) VALUES (NOW(), :rnd, :ip, :ua)");
    $stmt->execute([':rnd' => $rnd, ':ip' => $ip, ':ua' => $ua]);

    $lastId = (int)$pdo->lastInsertId();
    echo "🧪 В тестовую таблицу `{$testTable}` добавлена запись. ID={$lastId}, RND={$rnd}\n\n";
} catch (Throwable $e) {
    echo "⚠️ Не удалось создать/заполнить тестовую таблицу:\n", $e->getMessage(), "\n\n";
}

// ---------- 4) Выводим список таблиц и данные ----------
echo "===== Список таблиц (BASE TABLE) =====\n";

try {
    // Покажем только обычные таблицы (без VIEW)
    $tables = [];
    $stmt = $pdo->query("SHOW FULL TABLES");
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);

    // В первой колонке — имя таблицы, во второй — тип ('BASE TABLE' / 'VIEW')
    foreach ($rows as $r) {
        if (!empty($r[0]) && (!isset($r[1]) || strtoupper($r[1]) === 'BASE TABLE')) {
            $tables[] = $r[0];
        }
    }

    if (!$tables) {
        echo "(Нет таблиц в базе `{$DB_NAME}`)\n";
    } else {
        foreach ($tables as $t) {
            echo "\n--- Таблица: {$t} ---\n";

            // Схема
            try {
                $createRow = $pdo->query("SHOW CREATE TABLE `{$t}`")->fetch(PDO::FETCH_ASSOC);
                if ($createRow) {
                    $createSQL = $createRow['Create Table'] ?? reset($createRow);
                    echo "[DDL]\n", $createSQL, "\n";
                }
            } catch (Throwable $e) {
                echo "[DDL] Ошибка: ", $e->getMessage(), "\n";
            }

            // Кол-во строк
            try {
                $cnt = (int)$pdo->query("SELECT COUNT(*) AS c FROM `{$t}`")->fetchColumn();
                echo "[ROWS] Всего записей: {$cnt}\n";
            } catch (Throwable $e) {
                echo "[ROWS] Ошибка подсчёта: ", $e->getMessage(), "\n";
            }

            // Данные (выгружаем все постранично по 1000 строк, чтобы не упасть по памяти)
            $pageSize = 1000;
            $offset   = 0;
            $page     = 1;

            while (true) {
                try {
                    $dataStmt = $pdo->query("SELECT * FROM `{$t}` LIMIT {$pageSize} OFFSET {$offset}");
                    $batch = $dataStmt->fetchAll();
                } catch (Throwable $e) {
                    echo "[DATA] Ошибка выборки: ", $e->getMessage(), "\n";
                    break;
                }

                if (!$batch) {
                    if ($offset === 0) {
                        echo "[DATA] (таблица пуста)\n";
                    }
                    break;
                }

                echo "[DATA] Страница {$page} (записей: " . count($batch) . ")\n";
                // Печатаем как JSON для удобного копирования
                echo json_encode($batch, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";

                $offset += $pageSize;
                $page++;
            }
        }
    }
} catch (Throwable $e) {
    echo "ОШИБКА при перечислении таблиц/данных:\n", $e->getMessage(), "\n";
}

echo "\nГотово.\n";
