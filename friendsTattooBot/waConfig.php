<?php


$GRAPH_VERSION = 'v21.0';

//whatsapp
$WABA_ID = '1852405798675477';
$PHONE_NUMBER_ID = '700570583149082';
$DEFAULT_TO = '+4917632565824'; // твой основной номер
$FORWARD_TO = '+4917632565824';   // ваш личный номер WhatsApp
$PIN = '479812';     // твой 6-значный PIN двухэтапной проверки
$CERTIFICATE_B64 = '';           // для Cloud API оставить пустым (нужен только при миграции с BSP)

$TEMPLATE_NAME = 'hello_world';
$TEMPLATE_LANG = 'en_US';          // стандартный язык шаблона

$APP_ID = '1123610443025530';                 
$APP_SECRET = 'e3efa58fff48c69c9290e0fffa12de3f'; 

$ACCESS_TOKEN = 'EAAP96vFSjHoBPHJIoDaFwDCazs4pC8bVo4e9j2ZBY8GZBp6rDJ0sGscjZCD97tHVS5fGy4pnzu7ZBWZAxcDNu60jivDfhOX8YSGLBCNwIMu295sEL1aNZAHzAh50fq6KyPwZAUZBf3sVH9ZBMXSWTBMO4UW7Yfr4szPqLF5pccChQIoo9goPZAzkuf38xcxU8GzwZDZD'; // токен, КОТОРЫЙ уже видел этот WABA

$SYSTEM_USER_TOKEN = 'EAAP96vFSjHoBPHJIoDaFwDCazs4pC8bVo4e9j2ZBY8GZBp6rDJ0sGscjZCD97tHVS5fGy4pnzu7ZBWZAxcDNu60jivDfhOX8YSGLBCNwIMu295sEL1aNZAHzAh50fq6KyPwZAUZBf3sVH9ZBMXSWTBMO4UW7Yfr4szPqLF5pccChQIoo9goPZAzkuf38xcxU8GzwZDZD';

$CALLBACK_URL = 'https://friends-langenfeld.tattoo/friendsTattooBot/webHook.php';
$VERIFY_TOKEN = 'FbHook_9x@R2tZ!7mLpQ3vWfD6sU';


const VERIFY_TOKEN = 'FbHook_9x@R2tZ!7mLpQ3vWfD6sU';
const ADMIN_PASS = 'Adm#K7p!w29Qf4ZrS3^Lm0vB';
const MAX_FAILS = 5;
const LOCK_SECONDS = 300;
const LOG_DIR = __DIR__ . '/logs';
const REQ_LOG = LOG_DIR . '/requests.log';
const AUTH_DIR = LOG_DIR . '/auth_attempts';

