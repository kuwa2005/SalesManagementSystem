<?php
// アプリ設定
define('APP_NAME', '販売管理システム');
define('APP_VERSION', '1.0.0');
define('APP_ROOT', dirname(__DIR__, 2));
define('PUBLIC_PATH', APP_ROOT . '/public');
define('VIEW_PATH', APP_ROOT . '/views');
define('UPLOAD_PATH', PUBLIC_PATH . '/uploads');

// セッション設定
define('SESSION_LIFETIME', 3600 * 8); // 8時間

// 無料版制限
define('FREE_PLAN_MAX_SALES_LINES', 1000);
define('FREE_PLAN_MAX_USERS', 3);

// 年度選択可能数
define('MAX_FISCAL_YEARS', 7);

// 伝票明細上限（推定）
define('MAX_SALES_DETAIL_LINES', 800);
