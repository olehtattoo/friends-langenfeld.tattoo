<?php

require_once __DIR__ . '/waDbConfig.php';

function wa_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO)
        return $pdo;

    $dsn = $GLOBALS['DB_DSN'] ?? '';
    $user = $GLOBALS['DB_USER'] ?? '';
    $pass = $GLOBALS['DB_PASS'] ?? '';

    if ($dsn === '') {
        throw new RuntimeException('DB_DSN is not configured');
    }

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        // PDO::ATTR_PERSISTENT      => true, // включайте при необходимости
    ]);
    return $pdo;
}

/**
 * Возвращает имя таблицы с сообщениями WhatsApp.
 * Логика приоритета:
 * 1) $GLOBALS['DB_TABLE_NAME_WHATSAPP'] (если задана явно)
 * 2) 'wa_msg_whatsapp' в $GLOBALS['DB_TABLES_WA_BOT'] или $GLOBALS['DB_TABLES']
 * 3) Любая таблица, у которой в названии есть 'whatsapp' и есть колонка 'message_id'
 * 4) Иначе: '' (пустая строка)
 *
 * @param array|null $schema Необязательно: схема таблиц (по умолчанию возьмётся из глобалок)
 * @return string
 */
function wa_table_whatsapp_messages(?array $schema = null): string
{
    $tables = $GLOBALS['DB_TABLES_WA_BOT'] ?? [];
    $table = $tables ? array_keys($tables)[0] : '';
    return $table;
}

/** Квотирование идентификатора (имя таблицы/индекса/колонки) обратными апострофами. */
function wa_db_qi(string $ident): string
{
    return '`' . str_replace('`', '``', $ident) . '`';
}
function wa_db_qi_list(array $idents): string
{
    return implode(', ', array_map('wa_db_qi', $idents));
}

// --- ГЕНЕРАЦИЯ CREATE TABLE (без внешних ключей) --------------------------------

/**
 * Построить SQL для CREATE TABLE IF NOT EXISTS по описанию.
 * Поддержка: columns, primary, uniques, indexes, engine, charset, collate.
 * Внешние ключи сознательно НЕ включаем — их добавим отдельными ALTER.
 */
function wa_db_build_create_table_sql(string $table, array $def): string
{
    $engine = $def['engine'] ?? 'InnoDB';
    $charset = $def['charset'] ?? 'utf8mb4';
    $collate = $def['collate'] ?? 'utf8mb4_unicode_ci';

    $columns = $def['columns'] ?? [];
    if (!$columns || !is_array($columns)) {
        throw new InvalidArgumentException("Table '{$table}': 'columns' array is required");
    }

    $lines = [];

    // колонки
    foreach ($columns as $col => $ddl) {
        $lines[] = wa_db_qi($col) . ' ' . trim((string) $ddl);
    }

    // PRIMARY KEY
    if (!empty($def['primary'])) {
        $pk = is_array($def['primary']) ? $def['primary'] : [$def['primary']];
        $lines[] = 'PRIMARY KEY (' . wa_db_qi_list($pk) . ')';
    }

    // UNIQUE KEY
    if (!empty($def['uniques']) && is_array($def['uniques'])) {
        foreach ($def['uniques'] as $name => $cols) {
            $cols = is_array($cols) ? $cols : [$cols];
            $lines[] = 'UNIQUE KEY ' . wa_db_qi((string) $name) . ' (' . wa_db_qi_list($cols) . ')';
        }
    }

    // обычные индексы
    if (!empty($def['indexes']) && is_array($def['indexes'])) {
        foreach ($def['indexes'] as $name => $cols) {
            $cols = is_array($cols) ? $cols : [$cols];
            $lines[] = 'KEY ' . wa_db_qi((string) $name) . ' (' . wa_db_qi_list($cols) . ')';
        }
    }

    $sql = 'CREATE TABLE IF NOT EXISTS ' . wa_db_qi($table) . " (\n  "
        . implode(",\n  ", $lines)
        . "\n) ENGINE={$engine} DEFAULT CHARSET={$charset} COLLATE={$collate};";

    return $sql;
}

/** Сформировать ALTER TABLE ADD CONSTRAINT для внешнего ключа. */
function wa_db_build_add_fk_sql(string $table, string $fkName, array $fk): string
{
    $cols = (array) ($fk['columns'] ?? []);
    $refT = (string) ($fk['ref_table'] ?? '');
    $refC = (array) ($fk['ref_columns'] ?? []);
    if (!$cols || !$refT || !$refC) {
        throw new InvalidArgumentException("FK '{$fkName}' for table '{$table}' is incomplete");
    }

    $sql = 'ALTER TABLE ' . wa_db_qi($table)
        . ' ADD CONSTRAINT ' . wa_db_qi($fkName)
        . ' FOREIGN KEY (' . wa_db_qi_list($cols) . ')'
        . ' REFERENCES ' . wa_db_qi($refT)
        . ' (' . wa_db_qi_list($refC) . ')';

    if (!empty($fk['on_delete']))
        $sql .= ' ON DELETE ' . strtoupper((string) $fk['on_delete']);
    if (!empty($fk['on_update']))
        $sql .= ' ON UPDATE ' . strtoupper((string) $fk['on_update']);

    $sql .= ';';
    return $sql;
}

// --- ОСНОВНАЯ: создать все таблицы по схеме(ам) ---------------------------------

/**
 * Создаёт таблицы по схеме. Если $tables не задан — берём объединение
 * $GLOBALS['DB_TABLES'] и $GLOBALS['DB_TABLES_WA_BOT'] (если есть).
 * 1) CREATE TABLE IF NOT EXISTS (без внешних ключей)
 * 2) ALTER TABLE ... ADD CONSTRAINT для каждого foreign (ошибки duplicate — игнорируются)
 */
function wa_db_ensure_tables(?array $tables = null): void
{
    // объединяем схемы из глобалок, если не передали явно
    if ($tables === null) {
        $a = (array) ($GLOBALS['DB_TABLES'] ?? []);
        $b = (array) ($GLOBALS['DB_TABLES_WA_BOT'] ?? []);
        // при совпадении имён — $tables имеет приоритет $a, затем добиваем из $b
        $tables = $a + $b;
    }
    if (!$tables)
        return;

    $pdo = wa_db();

    // 1) создаём таблицы без внешних ключей
    foreach ($tables as $name => $def) {
        $name = (string) $name;
        $def = (array) $def;
        $sql = wa_db_build_create_table_sql($name, $def);
        $pdo->exec($sql);
    }

    // 2) добавляем внешние ключи отдельными ALTER TABLE
    foreach ($tables as $name => $def) {
        if (empty($def['foreign']) || !is_array($def['foreign']))
            continue;

        foreach ($def['foreign'] as $fkName => $fkDef) {
            $fkName = (string) $fkName;
            $fkDef = (array) $fkDef;

            $sql = wa_db_build_add_fk_sql((string) $name, $fkName, $fkDef);
            try {
                $pdo->exec($sql);
            } catch (Throwable $e) {
                // Если FK уже существует или ref-таблица не подходит — не падаем.
                // По желанию здесь можно логировать: wa_log_error(...)
                $msg = $e->getMessage();
                // дубликаты/существует — часто 1826/1005/1022 и пр., просто проглатываем
                continue;
            }
        }
    }
}

/**
 * Генерирует «длинный» код (по умолчанию 20 hex = 10 байт), проверяет на уникальность
 * в таблице wa_bot_code_messages и РЕЗЕРВИРУЕТ запись (INSERT).
 *
 * @param array $seed  Доп. данные для резервации (platform, user_id, to_id, direction, type, text_body, payload_json, msg_ts, context_id, conversation_id, status)
 * @param int   $bytes Длина в байтах для random_bytes (>=10 рекомендуется).
 * @return string Сгенерированный уникальный CODE (UPPER HEX).
 * @throws RuntimeException если после нескольких попыток не удалось зарезервировать.
 */
function wa_menu_code_reserve(array $seed = [], int $bytes = 10): string
{
    //имя таблицы
    $table = wa_table_whatsapp_messages();

    // гарантируем схему
    if (function_exists('wa_db_ensure_tables')) {
        wa_db_ensure_tables($GLOBALS['DB_TABLES_WA_BOT'] ?? []);
    }

    $pdo = function_exists('wa_db') ? wa_db() : new PDO($GLOBALS['DB_DSN'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // нормализуем вход
    $allowed = [
        'platform',
        'user_id',
        'to_id',
        'direction',
        'type',
        'text_body',
        'payload_json',
        'msg_ts',
        'context_id',
        'conversation_id',
        'status'
    ];
    $data = array_intersect_key($seed, array_flip($allowed));
    $data = array_map(fn($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $v, $data);

    // до 8 попыток (в случае гонки уникального PK)
    for ($attempt = 0; $attempt < 8; $attempt++) {
        $code = strtoupper(bin2hex(random_bytes(max(10, $bytes)))); // 20+ hex символов

        try {
            // INSERT
            $cols = array_merge(['code'], array_keys($data));
            $ph = array_map(fn($c) => ':' . $c, $cols);

            $sql = 'INSERT INTO `' . $table . '` (' . implode(',', array_map(fn($c) => '`' . $c . '`', $cols)) . ') '
                . 'VALUES (' . implode(',', $ph) . ')';

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':code', $code);
            foreach ($data as $k => $v)
                $stmt->bindValue(':' . $k, $v);
            $stmt->execute();

            // успешно зарезервировали
            return $code;
        } catch (Throwable $e) {
            // если конфликты по PK — пробуем ещё
            $msg = $e->getMessage();
            if (stripos($msg, 'duplicate') !== false || stripos($msg, 'Integrity constraint') !== false) {
                continue;
            }
            // иные ошибки — бросаем
            throw $e;
        }
    }

    throw new RuntimeException('Failed to reserve unique code after several attempts');
}

/**
 * Привязать meta message_id и (опционально) сырой payload к уже зарезервированному CODE.
 */
function wa_menu_code_bind_message(string $code, ?string $messageId, ?array $payload = null, ?int $ts = null, ?string $status = null): void
{
    $table = wa_table_whatsapp_messages();
    if (function_exists('wa_db_ensure_tables')) {
        wa_db_ensure_tables($GLOBALS['DB_TABLES_WA_BOT'] ?? []);
    }
    $pdo = function_exists('wa_db') ? wa_db() : new PDO($GLOBALS['DB_DSN'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $sql = 'UPDATE `' . $table . '`
            SET `message_id` = :mid,
                `payload_json` = COALESCE(:payload, `payload_json`),
                `msg_ts` = COALESCE(:ts, `msg_ts`),
                `status` = COALESCE(:st, `status`)
            WHERE `code` = :code
            LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':mid', $messageId);
    $stmt->bindValue(':payload', $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null);
    $stmt->bindValue(':ts', $ts, PDO::PARAM_INT);
    $stmt->bindValue(':st', $status);
    $stmt->bindValue(':code', $code);
    $stmt->execute();
}

/**
 * Разбирает собственное сообщение-ответ с кодом (095E... N ...),
 * обновляет/создаёт запись в таблице сообщений по code и
 * ВОЗВРАЩАЕТ структуру для дальнейшей обработки.
 *
 * Возвращает массив:
 * [
 *   ok              => bool,
 *   code            => string,
 *   command_num     => int,
 *   command_key     => string|null,        // из $GLOBALS['MESSAGE_BUTTONS_USED'][N-1] если есть
 *   allows_text     => bool,               // из $GLOBALS['MESSAGE_BUTTONS'][key]['text_answer_possible']
 *   user_text       => string,             // текст после кода/номера (если был)
 *   has_text        => bool,
 *   reply_message_id=> string|null,        // wamid нашего «ответа-управления»
 *   reply_ts        => int|null,
 *   original        => [                   // исходная запись, которую «метили» кодом при отправке меню
 *      platform, user_id, to_id, direction, text_body, message_id, msg_ts
 *   ]|null,
 *   target_user_id  => string|null,        // куда отвечать клиенту (обычно original.user_id)
 * ]
 */
function wa_bot_handle_own_message_code(array $fmt, string $rawBody): array
{
    $t0 = microtime(true);
    $fn = __FUNCTION__;
    wa_log_enter($fn, [
        'platform'     => $fmt['platform'] ?? '',
        'sender_id'    => $fmt['sender_id'] ?? '',
        'text_preview' => _cut((string)($fmt['text'] ?? ''), 160),
    ]);

    $ret = [
        'ok'               => false,
        'code'             => '',
        'command_num'      => 0,
        'command_key'      => null,
        'allows_text'      => false,
        'user_text'        => '',
        'has_text'         => false,
        'reply_message_id' => null,
        'reply_ts'         => null,
        'original'         => null,
        'target_user_id'   => null,
    ];

    try {
        if (($fmt['platform'] ?? '') !== 'whatsapp') {
            wa_log_warn("$fn: unsupported platform", ['platform' => $fmt['platform'] ?? '']);
            return $ret;
        }

        // Парсим код/номер/текст
        $parsed = wa_parse_prefill_command((string)($fmt['text'] ?? '')); // ваша улучшенная версия
        wa_log_info("$fn: parsed", $parsed);

        if (($parsed['code'] ?? '') === '' || ($parsed['num'] ?? 0) < 1) {
            wa_log_error("$fn: no valid command", ['text' => _cut((string)($fmt['text'] ?? ''), 300)]);
            return $ret;
        }

        // Определяем ключ команды по позиции в MESSAGE_BUTTONS_USED
        $key = null; $allows = false;
        $used = (array)($GLOBALS['MESSAGE_BUTTONS_USED'] ?? []);
        $map  = (array)($GLOBALS['MESSAGE_BUTTONS'] ?? []);
        $idx  = (int)$parsed['num'] - 1;
        if (isset($used[$idx])) {
            $key = (string)$used[$idx];
            $allows = !empty($map[$key]['text_answer_possible']);
        }

        $ret['code']        = strtoupper($parsed['code']);
        $ret['command_num'] = (int)$parsed['num'];
        $ret['command_key'] = $key;
        $ret['allows_text'] = (bool)$allows;
        $ret['user_text']   = (string)($parsed['rest'] ?? '');
        $ret['has_text']    = ($ret['user_text'] !== '');

        // БД и таблица
        $table = wa_table_whatsapp_messages();
        if ($table === '') {
            wa_log_error("$fn: no whatsapp messages table configured");
            return $ret;
        }
        wa_log_info("$fn: using table", ['table' => $table]);

        if (function_exists('wa_db_ensure_tables')) {
            try { wa_db_ensure_tables($GLOBALS['DB_TABLES_WA_BOT'] ?? []); } catch (\Throwable $e) {}
        }
        $pdo = function_exists('wa_db') ? wa_db() : new PDO($GLOBALS['DB_DSN'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASS'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // Идентификаторы нашего управляющего сообщения (то, где пришёл код)
        $replyMid = (string)(($fmt['message_ids'][0] ?? '') ?: ($fmt['message_id'] ?? ''));
        $replyTs  = (int)($fmt['ts_epoch'] ?? $fmt['ts'] ?? 0);
        $ret['reply_message_id'] = $replyMid ?: null;
        $ret['reply_ts']         = $replyTs ?: null;

        // Обновляем запись по code
        $sql = 'UPDATE `'.$table.'`
                SET `reply_message_id`   = :rid,
                    `reply_text`         = :rtext,
                    `reply_payload_json` = :rjson,
                    `reply_ts`           = :rts,
                    `command_num`        = :num,
                    `needs_text`         = :need
                WHERE `code` = :code
                LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':rid'  => $ret['reply_message_id'],
            ':rtext'=> (string)($fmt['text'] ?? ''),
            ':rjson'=> $rawBody ? (string)_cut($rawBody, 4000) : null,
            ':rts'  => $ret['reply_ts'],
            ':num'  => $ret['command_num'],
            ':need' => (int)$ret['allows_text'],
            ':code' => $ret['code'],
        ]);

        if ($stmt->rowCount() === 0) {
            // если не было «брони» — создадим минимальную запись
            $ins = $pdo->prepare('INSERT IGNORE INTO `'.$table.'`
                (`code`,`platform`,`direction`,`user_id`,`to_id`,
                 `reply_message_id`,`reply_text`,`reply_payload_json`,`reply_ts`,
                 `command_num`,`needs_text`,`created_at`)
                VALUES (:code,:pf,:dir,:uid,:to,:rid,:rtext,:rjson,:rts,:num,:need,CURRENT_TIMESTAMP)');
            $ins->execute([
                ':code' => $ret['code'],
                ':pf'   => 'whatsapp',
                ':dir'  => 'in',
                ':uid'  => (string)($fmt['sender_id'] ?? ''),                      // кто прислал код (оператор)
                ':to'   => (string)($fmt['to'] ?? ($GLOBALS['PHONE_NUMBER_ID'] ?? '')),
                ':rid'  => $ret['reply_message_id'],
                ':rtext'=> (string)($fmt['text'] ?? ''),
                ':rjson'=> $rawBody ? (string)_cut($rawBody, 4000) : null,
                ':rts'  => $ret['reply_ts'],
                ':num'  => $ret['command_num'],
                ':need' => (int)$ret['allows_text'],
            ]);
        }

        // Забираем полную запись для кода — чтобы получить «исходный» текст и адресата
        $sel = $pdo->prepare('SELECT * FROM `'.$table.'` WHERE `code` = :code ORDER BY `updated_at` DESC LIMIT 1');
        $sel->execute([':code' => $ret['code']]);
        $row = $sel->fetch();
        if ($row) {
            $ret['original'] = [
                'platform'   => (string)($row['platform']   ?? ''),
                'user_id'    => (string)($row['user_id']    ?? ''), // клиентский wa_id (куда отвечать)
                'to_id'      => (string)($row['to_id']      ?? ''),
                'direction'  => (string)($row['direction']  ?? ''),
                'text_body'  => (string)($row['text_body']  ?? ''),
                'message_id' => (string)($row['message_id'] ?? ''),
                'msg_ts'     => isset($row['msg_ts']) ? (int)$row['msg_ts'] : null,
            ];

            // Куда отправлять реальный ответ клиенту — обычно original.user_id
            $target = (string)($row['user_id'] ?? '');
            $ret['target_user_id'] = $target !== '' ? $target : null;
        }

        $ret['ok'] = true;
        wa_log_return($fn, ['ok' => true, 'code' => $ret['code'], 'num' => $ret['command_num'], 'ms' => (int)((microtime(true)-$t0)*1000)]);
        return $ret;

    } catch (\Throwable $e) {
        wa_log_error("$fn: exception", [
            'type' => get_class($e),
            'msg'  => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
        return $ret;
    }
}


function wa_parse_prefill_command(string $text): array
{
    // 1) Ищем "CODE N Text: ...", где CODE = 8+ hex, N = 1..9
    if (preg_match_all('~([A-F0-9]{8,})\s+([1-9])\s*Text:\s*([^\r\n]*)~i', $text, $mm, PREG_SET_ORDER)) {
        $m = end($mm);
        return [
            'code'       => strtoupper($m[1]),
            'num'        => (int)$m[2],
            'rest'       => trim((string)($m[3] ?? '')),
            'needs_text' => true,
        ];
    }

    // 2) Легаси-вариант: "CODE N // ... "
    if (preg_match_all('~([A-F0-9]{8,})\s+([1-9])\s*//\s*([^\r\n]*)~i', $text, $mm, PREG_SET_ORDER)) {
        $m = end($mm);
        return [
            'code'       => strtoupper($m[1]),
            'num'        => (int)$m[2],
            'rest'       => trim((string)($m[3] ?? '')),
            'needs_text' => true,
        ];
    }

    // 3) Просто "CODE N" (после N — опциональный хвост до конца строки)
    if (preg_match_all('~([A-F0-9]{8,})\s+([1-9])(?:\s+([^\r\n]*))?~i', $text, $mm, PREG_SET_ORDER)) {
        $m = end($mm);
        return [
            'code'       => strtoupper($m[1]),
            'num'        => (int)$m[2],
            'rest'       => trim((string)($m[3] ?? '')),
            'needs_text' => false,
        ];
    }

    return ['code'=>'', 'num'=>0, 'rest'=>'', 'needs_text'=>false];
}

