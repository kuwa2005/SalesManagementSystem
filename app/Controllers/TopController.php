<?php
require_once __DIR__ . '/../Helpers/Session.php';
require_once __DIR__ . '/../Helpers/Database.php';
require_once __DIR__ . '/../Helpers/Auth.php';

Session::start();
Auth::requireLogin();
Auth::requireFiscalYear();

$permissions = Auth::getPermissions();
$isAdmin = Session::isAdmin();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>トップメニュー - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/SalesManagementSystem/public/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left"><h1><?= APP_NAME ?></h1></div>
        <div class="header-right">
            <span class="fiscal-year"><?= htmlspecialchars(Session::get('fiscal_year_label')) ?>年度</span>
            <span class="user-name"><?= htmlspecialchars(Session::get('user_name')) ?></span>
            <a href="/SalesManagementSystem/" class="btn btn-small">年度選択</a>
            <a href="/SalesManagementSystem/logout.php" class="btn btn-small">ログアウト</a>
        </div>
    </header>

    <main class="top-menu">
        <div class="menu-grid">

            <!-- 1. 日常業務：売上 -->
            <?php if ($isAdmin || isset($permissions['SAL'])): ?>
            <div class="menu-section">
                <h2>売上</h2>
                <div class="menu-buttons">
                    <?php if ($isAdmin || isset($permissions['SAL']['01'])): ?>
                    <a href="/SalesManagementSystem/sales/input.php" class="menu-btn">売上伝票入力</a>
                    <?php endif; ?>
                    <?php if ($isAdmin || isset($permissions['SAL']['02'])): ?>
                    <a href="/SalesManagementSystem/sales/search.php" class="menu-btn">売上伝票訂正・削除</a>
                    <?php endif; ?>
                    <?php if ($isAdmin || isset($permissions['SAL']['05'])): ?>
                    <a href="/SalesManagementSystem/sales/output.php" class="menu-btn">売上伝票出力</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- 2. 日常業務：請求 -->
            <?php if ($isAdmin || isset($permissions['INV'])): ?>
            <div class="menu-section">
                <h2>請求</h2>
                <div class="menu-buttons">
                    <?php if ($isAdmin || isset($permissions['INV']['01'])): ?>
                    <a href="/SalesManagementSystem/invoice/create.php" class="menu-btn">請求書作成</a>
                    <?php endif; ?>
                    <?php if ($isAdmin || isset($permissions['INV']['02'])): ?>
                    <a href="/SalesManagementSystem/invoice/output.php" class="menu-btn">請求書再出力</a>
                    <?php endif; ?>
                    <?php if ($isAdmin || isset($permissions['INV']['03'])): ?>
                    <a href="/SalesManagementSystem/invoice/release.php" class="menu-btn">請求締解除</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- 3. 日常業務：入金 -->
            <?php if ($isAdmin || isset($permissions['PAY'])): ?>
            <div class="menu-section">
                <h2>入金</h2>
                <div class="menu-buttons">
                    <?php if ($isAdmin || isset($permissions['PAY']['01'])): ?>
                    <a href="/SalesManagementSystem/payment/input.php" class="menu-btn">入金入力</a>
                    <?php endif; ?>
                    <?php if ($isAdmin || isset($permissions['PAY']['03'])): ?>
                    <a href="/SalesManagementSystem/payment/list.php" class="menu-btn">入金実績一覧</a>
                    <?php endif; ?>
                    <?php if ($isAdmin || isset($permissions['PAY']['04'])): ?>
                    <a href="/SalesManagementSystem/payment/receipt.php" class="menu-btn">領収書出力</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- 4. 照会 -->
            <?php if ($isAdmin || isset($permissions['INQ'])): ?>
            <div class="menu-section">
                <h2>照会</h2>
                <div class="menu-buttons">
                    <?php if ($isAdmin || isset($permissions['INQ']['01'])): ?>
                    <a href="/SalesManagementSystem/inquiry/customer.php" class="menu-btn">得意先マスタ照会</a>
                    <?php endif; ?>
                    <?php if ($isAdmin || isset($permissions['INQ']['02'])): ?>
                    <a href="/SalesManagementSystem/inquiry/product.php" class="menu-btn">商品マスタ照会</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- 5. 帳票・分析 -->
            <?php if ($isAdmin || isset($permissions['LED']) || isset($permissions['RPT']) || isset($permissions['ANA'])): ?>
            <div class="menu-section">
                <h2>帳票・分析</h2>
                <div class="menu-buttons">
                    <?php if ($isAdmin || isset($permissions['LED']['01'])): ?>
                    <a href="/SalesManagementSystem/report/ledger.php" class="menu-btn">得意先元帳</a>
                    <?php endif; ?>
                    <?php if ($isAdmin || isset($permissions['LED']['02'])): ?>
                    <a href="/SalesManagementSystem/report/balance.php" class="menu-btn">売掛残高一覧</a>
                    <?php endif; ?>
                    <?php if ($isAdmin || isset($permissions['RPT']['01'])): ?>
                    <a href="/SalesManagementSystem/report/sales-detail.php" class="menu-btn">売上明細表</a>
                    <?php endif; ?>
                    <?php if ($isAdmin || isset($permissions['RPT']['02'])): ?>
                    <a href="/SalesManagementSystem/report/daily.php" class="menu-btn">売上日報</a>
                    <?php endif; ?>
                    <?php if ($isAdmin || isset($permissions['RPT']['03'])): ?>
                    <a href="/SalesManagementSystem/report/monthly.php" class="menu-btn">売上月報</a>
                    <?php endif; ?>
                    <?php if ($isAdmin || isset($permissions['ANA']['01'])): ?>
                    <a href="/SalesManagementSystem/report/trend.php" class="menu-btn">売上推移表</a>
                    <?php endif; ?>
                    <?php if ($isAdmin || isset($permissions['ANA']['02'])): ?>
                    <a href="/SalesManagementSystem/report/ranking.php" class="menu-btn">売上順位表</a>
                    <?php endif; ?>
                    <?php if ($isAdmin || isset($permissions['ANA']['04'])): ?>
                    <a href="/SalesManagementSystem/report/analysis.php" class="menu-btn">売上分析表</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- 6. 基本情報・マスタ -->
            <?php if ($isAdmin || isset($permissions['MST'])): ?>
            <div class="menu-section">
                <h2>基本情報・マスタ</h2>
                <div class="menu-buttons">
                    <?php if ($isAdmin || isset($permissions['MST']['01'])): ?>
                    <a href="/SalesManagementSystem/master/company.php" class="menu-btn">基本情報登録</a>
                    <?php endif; ?>
                    <?php if ($isAdmin || isset($permissions['MST']['02'])): ?>
                    <a href="/SalesManagementSystem/master/department.php" class="menu-btn">自社部門マスタ</a>
                    <?php endif; ?>
                    <?php if ($isAdmin || isset($permissions['MST']['03'])): ?>
                    <a href="/SalesManagementSystem/master/staff.php" class="menu-btn">自社担当者マスタ</a>
                    <?php endif; ?>
                    <?php if ($isAdmin || isset($permissions['MST']['04'])): ?>
                    <a href="/SalesManagementSystem/master/payment-type.php" class="menu-btn">入金区分マスタ</a>
                    <?php endif; ?>
                    <?php if ($isAdmin || isset($permissions['MST']['05'])): ?>
                    <a href="/SalesManagementSystem/master/description.php" class="menu-btn">摘要マスタ</a>
                    <?php endif; ?>
                    <?php if ($isAdmin || isset($permissions['MST']['06'])): ?>
                    <a href="/SalesManagementSystem/master/customer.php" class="menu-btn">得意先マスタ</a>
                    <?php endif; ?>
                    <?php if ($isAdmin || isset($permissions['MST']['07'])): ?>
                    <a href="/SalesManagementSystem/master/category.php" class="menu-btn">商品カテゴリーマスタ</a>
                    <?php endif; ?>
                    <?php if ($isAdmin || isset($permissions['MST']['08'])): ?>
                    <a href="/SalesManagementSystem/master/product.php" class="menu-btn">商品マスタ</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- 7. 外部連携 -->
            <?php if ($isAdmin || isset($permissions['EXT'])): ?>
            <div class="menu-section">
                <h2>外部連携</h2>
                <div class="menu-buttons">
                    <?php if ($isAdmin || isset($permissions['EXT']['01'])): ?>
                    <a href="/SalesManagementSystem/external/accounting.php" class="menu-btn">会計データ出力</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- 8. 運用管理 -->
            <?php if ($isAdmin): ?>
            <div class="menu-section">
                <h2>運用管理</h2>
                <div class="menu-buttons">
                    <a href="/SalesManagementSystem/admin/year.php" class="menu-btn">年次繰越</a>
                    <a href="/SalesManagementSystem/admin/user-info.php" class="menu-btn">ユーザ情報変更</a>
                    <a href="/SalesManagementSystem/admin/users.php" class="menu-btn">ユーザ管理</a>
                    <a href="/SalesManagementSystem/admin/permission.php" class="menu-btn">権限管理</a>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </main>
</body>
</html>
