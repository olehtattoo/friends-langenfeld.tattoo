<?php declare(strict_types=1);
require_once __DIR__ . '/waLogDebug.php';

wa_log_info('test_info', ['hello' => 'world']);
wa_log_warn('test_warn', ['n' => 123]);
wa_log_error('test_error', ['reason' => 'just_check']);
echo "ok\n";