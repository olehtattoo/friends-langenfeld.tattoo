<?php declare(strict_types=1);

$GRAPH_VERSION = 'v21.0';

// WhatsApp
$WABA_ID         = '1852405798675477';
$PHONE_NUMBER_ID = '700570583149082';

$DEFAULT_TO = '+4917632565824';
$FORWARD_TO = '+4917632565824';

$PIN            = '479812';
$CERTIFICATE_B64 = ''; // для Cloud API пусто

// Templates
$TEMPLATE_NAME = 'hello_world';
$TEMPLATE_LANG = 'en_US';

// App
$APP_ID     = '1123610443025530';
$APP_SECRET = 'e3efa58fff48c69c9290e0fffa12de3f';

// Tokens
$ACCESS_TOKEN      = 'EAAP96vFSjHoBPHJIoDaFwDCazs4pC8bVo4e9j2ZBY8GZBp6rDJ0sGscjZCD97tHVS5fGy4pnzu7ZBWZAxcDNu60jivDfhOX8YSGLBCNwIMu295sEL1aNZAHzAh50fq6KyPwZAUZBf3sVH9ZBMXSWTBMO4UW7Yfr4szPqLF5pccChQIoo9goPZAzkuf38xcxU8GzwZDZD';
$SYSTEM_USER_TOKEN = 'EAAP96vFSjHoBPHJIoDaFwDCazs4pC8bVo4e9j2ZBY8GZBp6rDJ0sGscjZCD97tHVS5fGy4pnzu7ZBWZAxcDNu60jivDfhOX8YSGLBCNwIMu295sEL1aNZAHzAh50fq6KyPwZAUZBf3sVH9ZBMXSWTBMO4UW7Yfr4szPqLF5pccChQIoo9goPZAzkuf38xcxU8GzwZDZD';

// Webhook
$CALLBACK_URL = 'https://friends-langenfeld.tattoo/friendsTattooBot/webHook.php';
$VERIFY_TOKEN = 'FbHook_9x@R2tZ!7mLpQ3vWfD6sU';

// Admin/login anti-bruteforce
$ADMIN_PASS               = 'Adm#K7p!w29Qf4ZrS3^Lm0vB';
$ADMIN_LOGIN_MAX_FAILS    = 5;
$ADMIN_LOGIN_LOCK_SECONDS = 300;

/*$ADMIN_LOGIN_LOG_DIR  = __DIR__ . '/logs';
$ADMIN_LOGIN_REQ_LOG  = $ADMIN_LOGIN_LOG_DIR . '/requests.log';
$ADMIN_LOGIN_AUTH_DIR = $ADMIN_LOGIN_LOG_DIR . '/auth_attempts';*/

$WA_MAIN_LOG = __DIR__ . '/waMainLog.log';
$WA_MAIN_LOG_DEBUG  = true;


// Webhook fields (на будущее)
$WEBHOOK_FIELDS = [
  'messages',
  'message_template_status_update',
  'message_template_quality_update',
  'phone_number_name_update',
  'phone_number_quality_update',
  'account_update',
  'account_alerts',
  'account_review_update',
  'business_capability_update',
  'business_status_update',
  'flows',
  'automatic_events',
  'user_preferences',
];
