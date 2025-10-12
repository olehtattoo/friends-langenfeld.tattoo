<?php declare(strict_types=1);

// Общие настройки
$GLOBALS['FMT_CFG'] = [
    // бренд и язык по умолчанию
    'brand'            => 'Friends Tattoo Langenfeld',
    'default_locale'   => 'ru',

    // Названия сервисов
    'service_facebook' => 'Facebook',
    'service_instagram'=> 'Instagram',
    'service_whatsapp' => 'WhatsApp',

    // Метки/тексты (общие)
    'label_incoming'   => '📥 Новое сообщение {SERVICE}',
    'label_from'       => '👤 От: {SENDER}',
    'label_time'       => '🕒 Время: {TIME}',
    'label_type'       => '📨 Тип: {TYPE}',
    'label_text'       => "💬 Текст:\n{TEXT}",

    // Разделитель
    'separator'        => '———————————————----------------',

    // Тексты «нет входящих»
    'no_incoming_whatsapp'  => 'ℹ️ События получены, но входящих сообщений не обнаружено.',
    'no_incoming_facebook'  => 'ℹ️ Нет входящих сообщений Messenger.',
    'no_incoming_instagram' => 'ℹ️ Нет входящих сообщений Instagram.',

    // Читабельные названия типов (ключи — нормализованные типы)
    'type_text'        => 'текст',
    'type_image'       => 'фото',
    'type_video'       => 'видео',
    'type_audio'       => 'аудио',
    'type_document'    => 'документ',
    'type_file'        => 'файл',
    'type_sticker'     => 'стикер',
    'type_location'    => 'локация',
    'type_contacts'    => 'контакты',
    'type_button'      => 'кнопка',
    'type_interactive' => 'interactive',
    'type_quick_reply' => 'quick_reply',
    'type_postback'    => 'postback',
    'type_reaction'    => 'реакция',
    'type_unknown'     => 'неизвестно',

    // Паттерны ссылок
    'link_facebook_profile'  => 'https://facebook.com/profile.php?id=%s',
    'link_instagram_profile' => 'https://instagram.com/%s',
];


// whatsapp Кнопки и переводы
$GLOBALS['MESSAGE_BUTTONS'] = [
    'translate' => [
        'emoji'  => '🌐',
        'label'  => [
            'ru' => 'Перевести',
            'de' => 'Übersetzen',
            'en' => 'Translate',
        ],
        'text_answer_possible'  => false,
    ],
    'translate_simplify' => [
        'emoji'  => '✨',
        'label'  => [
            'ru' => 'Упростить',
            'de' => 'Vereinfachen',
            'en' => 'Simplify',
        ],
        'text_answer_possible'  => false,
    ],
    'answer' => [
        'emoji'  => '✍️',
        'label'  => [
            'ru' => 'Ответить',
            'de' => 'Antworten',
            'en' => 'Reply',
        ],
        'text_answer_possible'  => true,
    ],
    'answer_chatgpt' => [
        'emoji'  => '🤖',
        'label'  => [
            'ru' => 'Ответ через ChatGPT',
            'de' => 'Antwort mit ChatGPT',
            'en' => 'Reply via ChatGPT',
        ],
        'text_answer_possible'  => true,
    ],
    'answer_chatgpt_automatic' => [
        'emoji'  => '⚡',
        'label'  => [
            'ru' => 'Авто-ответ ChatGPT',
            'de' => 'Auto-Antwort ChatGPT',
            'en' => 'Auto-Reply (ChatGPT)',
        ],
        'text_answer_possible'  => false,
    ],
];

/**
 * Возвращает локализованный текст кнопки (с эмодзи) из $GLOBALS['MESSAGE_BUTTONS'].
 *
 * @param string $key   Ключ кнопки из глобалки.
 * @param string $lang  Язык, напр. 'de','ru','en','uk','pt' (по умолчанию 'de').
 * @param int    $maxLenМакс. длина для UI (0 = без ограничений). Полезно для WhatsApp (≈20).
 * @param bool   $withEmoji Включать эмодзи перед текстом.
 * @return string
 */

function msg_btn(string $key, string $lang = 'de', int $maxLen = 0, bool $withEmoji = true): string
{
    $map = $GLOBALS['MESSAGE_BUTTONS'] ?? [];
    if (!isset($map[$key])) {
        $txt = ucfirst(str_replace('_', ' ', $key));
    } else {
        $labels = $map[$key]['label'] ?? [];
        $emoji  = $map[$key]['emoji'] ?? '';
        $fallback = [$lang, 'de', 'en', 'ru', 'uk', 'pt'];

        $txt = null;
        foreach ($fallback as $l) {
            if (!empty($labels[$l])) { $txt = $labels[$l]; break; }
        }
        if ($txt === null) $txt = ucfirst(str_replace('_', ' ', $key));

        if ($withEmoji && $emoji !== '') {
            $txt = $emoji . ' ' . $txt;
        }
    }

    if ($maxLen > 0 && mb_strlen($txt) > $maxLen) {
        $txt = rtrim(mb_substr($txt, 0, max(1, $maxLen - 1))) . '…';
    }

    return $txt;
}


// whatsapp Кнопки которые реально используются в UI
$GLOBALS['MESSAGE_BUTTONS_USED'] = [
    'translate',
    'translate_simplify',
    'answer',
    'answer_chatgpt',
    'answer_chatgpt_automatic'
];

//реакции после нажатия на кропки
$GLOBALS['MESSAGE_BUTTONS_ANSWER'] = [
    'answer_chatgpt' => [
        'confirm' => [
            'ru' => 'Отправить ответ',
            'de' => 'Antwort senden',
            'en' => 'Send reply',
        ],
        'try_again' => [
            'ru' => 'Попробовать ещё раз',
            'de' => 'Erneut versuchen',
            'en' => 'Try again',
        ],
        'cancel' => [
            'ru' => 'Отмена',
            'de' => 'Abbrechen',
            'en' => 'Cancel',
        ],
    ],

    'answer_chatgpt_automatic' => [
        'confirm' => [
            'ru' => 'Отправить ответ',
            'de' => 'Antwort senden',
            'en' => 'Send reply',
        ],
        'try_again' => [
            'ru' => 'Попробовать ещё раз',
            'de' => 'Erneut versuchen',
            'en' => 'Try again',
        ],
        'add_new_pattern' => [
            'ru' => 'Добавить правило',
            'de' => 'Regel hinzufügen',
            'en' => 'Add rule',
        ],
        'cancel' => [
            'ru' => 'Отмена',
            'de' => 'Abbrechen',
            'en' => 'Cancel',
        ],
    ],
];