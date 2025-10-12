<?php

require_once __DIR__ . '/../../dbFriends/dbConfig.php';

$GLOBALS['DB_TABLES_WA_BOT'] = [
    'wa_bot_messages' => [
        'columns' => [
            // Наш уникальный короткий код (PK)
            'code'              => 'VARCHAR(32) NOT NULL',

            // Базовая маршрутизация
            'platform'          => "ENUM('whatsapp','messenger','instagram') NOT NULL DEFAULT 'whatsapp'",
            'user_id'           => 'VARCHAR(128) NULL',   // кто (клиент/вы)
            'to_id'             => 'VARCHAR(128) NULL',   // кому (ваш business id/phone_number_id/страница)
            'direction'         => "ENUM('in','out','sys') NOT NULL DEFAULT 'out'",

            // Структура события/сообщения
            'type'              => 'VARCHAR(32) NULL',    // text|interactive|status|...
            'status'            => 'VARCHAR(32) NULL',    // sent|delivered|read|failed|...
            'message_id'        => 'VARCHAR(191) NULL',   // meta id отправленного сообщения (после ответа от Meta)
            'context_id'        => 'VARCHAR(191) NULL',   // reply_to / context.message_id
            'conversation_id'   => 'VARCHAR(128) NULL',
            'msg_ts'            => 'BIGINT UNSIGNED NULL',

            // Содержимое
            'text_body'         => 'LONGTEXT NULL',
            'payload_json'      => 'LONGTEXT NULL',       // сырой payload именно этого сообщения

            // Информация по «команде» из ссылки (если прилетела от пользователя)
            'command_num'       => 'TINYINT NULL',        // 1..9
            'needs_text'        => 'TINYINT(1) NOT NULL DEFAULT 0', // ожидали ли текст (по ссылке было " // ")
            'reply_message_id'  => 'VARCHAR(191) NULL',   // meta id входящего сообщения с кодом
            'reply_text'        => 'LONGTEXT NULL',
            'reply_payload_json'=> 'LONGTEXT NULL',
            'reply_ts'          => 'BIGINT UNSIGNED NULL',

            // Таймстемпы
            'created_at'        => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at'        => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ],
        'primary' => ['code'],
        'indexes' => [
            'ix_msg'           => ['message_id'],
            'ix_reply_msg'     => ['reply_message_id'],
            'ix_created'       => ['created_at'],
            'ix_user'          => ['user_id'],
            'ix_conv'          => ['conversation_id'],
        ],
        'engine'  => 'InnoDB',
        'charset' => 'utf8mb4',
        'collate' => 'utf8mb4_unicode_ci',
    ],
];