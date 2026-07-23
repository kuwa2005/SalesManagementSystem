<?php
// エントリーポイント
require_once __DIR__ . '/../app/Config/app.php';
require_once __DIR__ . '/../app/Config/database.php';

// セッション開始
require_once __DIR__ . '/../app/Helpers/Session.php';
Session::start();

// 基本ヘルパー読み込み
require_once __DIR__ . '/../app/Helpers/Database.php';
require_once __DIR__ . '/../app/Helpers/Auth.php';
require_once __DIR__ . '/../app/Helpers/Csrf.php';
require_once __DIR__ . '/../app/Helpers/Validator.php';
require_once __DIR__ . '/../app/Helpers/Numbering.php';
require_once __DIR__ . '/../app/Helpers/TaxCalculator.php';

// モデル読み込み
require_once __DIR__ . '/../app/Models/BaseModel.php';

// ルーティング
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rtrim($path, '/');

// デフォルトはトップページ
if ($path === '' || $path === '/') {
    if (Auth::isLoggedIn()) {
        if (Session::getFiscalYearId()) {
            require __DIR__ . '/../app/Controllers/TopController.php';
        } else {
            require __DIR__ . '/../app/Controllers/Auth/YearController.php';
        }
    } else {
        require __DIR__ . '/../app/Controllers/Auth/LoginController.php';
    }
    exit;
}

// 静的ファイルは配信
if (file_exists(__DIR__ . $path)) {
    return false;
}

// 各画面へのルーティング
$routes = [
    '/login' => __DIR__ . '/../app/Controllers/Auth/LoginController.php',
    '/logout' => __DIR__ . '/../app/Controllers/Auth/LogoutController.php',
    '/year-select' => __DIR__ . '/../app/Controllers/Auth/YearController.php',
    '/top' => __DIR__ . '/../app/Controllers/TopController.php',
    '/password-reset' => __DIR__ . '/../app/Controllers/Auth/PasswordResetController.php',
];

// ルートに一致するか
foreach ($routes as $route => $file) {
    if ($path === $route && file_exists($file)) {
        require $file;
        exit;
    }
}

// 404
http_response_code(404);
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>404</title></head><body><h1>404 Not Found</h1><p><a href="/">トップへ</a></p></body></html>';
