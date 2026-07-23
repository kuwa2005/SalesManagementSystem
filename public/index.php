<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// エントリーポイント
// app/, sql/, views/ は SalesManagementSystem/ 直下にある
require_once __DIR__ . '/app/Config/app.php';
require_once __DIR__ . '/app/Config/database.php';

// セッション開始
require_once __DIR__ . '/app/Helpers/Session.php';
Session::start();

// 基本ヘルパー読み込み
require_once __DIR__ . '/app/Helpers/Database.php';
require_once __DIR__ . '/app/Helpers/Auth.php';
require_once __DIR__ . '/app/Helpers/Csrf.php';
require_once __DIR__ . '/app/Helpers/Validator.php';
require_once __DIR__ . '/app/Helpers/Numbering.php';
require_once __DIR__ . '/app/Helpers/TaxCalculator.php';

// モデル読み込み
require_once __DIR__ . '/app/Models/BaseModel.php';

// ルーティング
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rtrim($path, '/');
$base = '/SalesManagementSystem';
$path = str_replace($base, '', $path);
// .php拡張子を除去
$path = preg_replace('/\.php$/', '', $path);

// デフォルトはトップページ
if ($path === '' || $path === '/') {
    if (Session::isLoggedIn()) {
        if (Session::getFiscalYearId()) {
            require __DIR__ . '/app/Controllers/TopController.php';
        } else {
            require __DIR__ . '/app/Controllers/Auth/YearController.php';
        }
    } else {
        require __DIR__ . '/app/Controllers/Auth/LoginController.php';
    }
    exit;
}

// 静的ファイルは配信
if (file_exists(__DIR__ . '/public' . $path)) {
    return false;
}

// 各画面へのルーティング
$routes = [
    '/login' => __DIR__ . '/app/Controllers/Auth/LoginController.php',
    '/logout' => __DIR__ . '/app/Controllers/Auth/LogoutController.php',
    '/year-select' => __DIR__ . '/app/Controllers/Auth/YearController.php',
    '/top' => __DIR__ . '/app/Controllers/TopController.php',
    '/password-reset' => __DIR__ . '/app/Controllers/Auth/PasswordResetController.php',
    '/master/company' => __DIR__ . '/app/Controllers/Master/CompanyController.php',
    '/master/department' => __DIR__ . '/app/Controllers/Master/DepartmentController.php',
    '/master/staff' => __DIR__ . '/app/Controllers/Master/StaffController.php',
    '/master/payment-type' => __DIR__ . '/app/Controllers/Master/PaymentTypeController.php',
    '/master/description' => __DIR__ . '/app/Controllers/Master/DescriptionController.php',
    '/master/customer' => __DIR__ . '/app/Controllers/Master/CustomerController.php',
    '/master/category' => __DIR__ . '/app/Controllers/Master/CategoryController.php',
    '/master/product' => __DIR__ . '/app/Controllers/Master/ProductController.php',
    '/sales/input' => __DIR__ . '/app/Controllers/Sales/SalesController.php',
    '/sales/search' => __DIR__ . '/app/Controllers/Sales/SalesSearchController.php',
    '/sales/output' => __DIR__ . '/app/Controllers/Sales/SalesOutputController.php',
    '/invoice/create' => __DIR__ . '/app/Controllers/Invoice/InvoiceController.php',
    '/payment/input' => __DIR__ . '/app/Controllers/Payment/PaymentController.php',
    '/report/ledger' => __DIR__ . '/app/Controllers/Report/LedgerController.php',
    '/report/balance' => __DIR__ . '/app/Controllers/Report/BalanceController.php',
    '/report/sales-detail' => __DIR__ . '/app/Controllers/Report/SalesDetailController.php',
    '/external/accounting' => __DIR__ . '/app/Controllers/External/AccountingController.php',
    '/admin/year' => __DIR__ . '/app/Controllers/Admin/YearController.php',
    '/admin/user-info' => __DIR__ . '/app/Controllers/Admin/UserInfoController.php',
    '/admin/users' => __DIR__ . '/app/Controllers/Admin/UserController.php',
    '/admin/permission' => __DIR__ . '/app/Controllers/Admin/PermissionController.php',
    '/invoice/output' => __DIR__ . '/app/Controllers/Invoice/InvoiceOutputController.php',
    '/invoice/release' => __DIR__ . '/app/Controllers/Invoice/InvoiceReleaseController.php',
    '/payment/list' => __DIR__ . '/app/Controllers/Payment/PaymentListController.php',
    '/payment/receipt' => __DIR__ . '/app/Controllers/Payment/ReceiptController.php',
    '/report/daily' => __DIR__ . '/app/Controllers/Report/DailyController.php',
    '/report/monthly' => __DIR__ . '/app/Controllers/Report/MonthlyController.php',
    '/report/trend' => __DIR__ . '/app/Controllers/Report/TrendController.php',
    '/report/ranking' => __DIR__ . '/app/Controllers/Report/RankingController.php',
    '/report/analysis' => __DIR__ . '/app/Controllers/Report/AnalysisController.php',
    '/inquiry/customer' => __DIR__ . '/app/Controllers/Inquiry/CustomerInquiryController.php',
    '/inquiry/product' => __DIR__ . '/app/Controllers/Inquiry/ProductInquiryController.php',
];

foreach ($routes as $route => $file) {
    if ($path === $route && file_exists($file)) {
        require $file;
        exit;
    }
}

http_response_code(404);
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>404</title></head><body><h1>404 Not Found</h1><p><a href="/SalesManagementSystem/">トップへ</a></p></body></html>';
