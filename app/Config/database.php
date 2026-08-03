<?php
// データベース接続設定（値は .env から読み込む）
require_once __DIR__ . '/../Helpers/Env.php';
Env::load();

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', ''));
define('DB_USER', env('DB_USER', ''));
define('DB_PASS', env('DB_PASS', ''));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));
