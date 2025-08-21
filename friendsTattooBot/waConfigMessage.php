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
