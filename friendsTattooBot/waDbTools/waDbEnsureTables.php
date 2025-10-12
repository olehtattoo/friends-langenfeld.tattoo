<?php
//friendsTattooBot/waDbTools/waDbEnsureTables.php
require_once __DIR__ . '/../waDebug/waCrashCatcher.php';
require_once __DIR__ . '/waDbConfig.php';
require_once __DIR__ . '/waDbHelper.php';

wa_db_ensure_tables();
echo "Schema ensured\n";