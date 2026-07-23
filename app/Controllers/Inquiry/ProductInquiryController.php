<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';

Session::start();
Auth::requireLogin();
Auth::requireFiscalYear();

$db = Database::getConnection();
$scope = [Session::getTenantId(), Session::getFiscalYearId()];
$code = $_GET['code'] ?? '';
$product = null;

if ($code) {
    $stmt = $db->prepare("SELECT * FROM products WHERE tenant_id = ? AND fiscal_year_id = ? AND product_code = ?");
    $stmt->execute([$scope[0], $scope[1], $code]);
    $product = $stmt->fetch();
}

$stmt = $db->prepare("SELECT product_code, product_name FROM products WHERE tenant_id = ? AND fiscal_year_id = ? ORDER BY product_name_kana");
$stmt->execute($scope);
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><title>商品マスタ照会 - 販売管理システム</title>
    <link rel="stylesheet" href="/SalesManagementSystem/public/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left"><a href="/SalesManagementSystem/" style="font-size:18px;color:#1e293b;">販売管理システム</a> <span style="margin-left:16px;color:#64748b;">商品マスタ照会</span></div>
        <div class="header-right"><span class="fiscal-year"><?= htmlspecialchars(Session::get('fiscal_year_label')) ?>年度</span><a href="/SalesManagementSystem/" class="btn btn-small btn-secondary">TOP</a></div>
    </header>
    <main style="padding:24px;max-width:900px;margin:0 auto;">
        <div class="search-form">
            <form method="get">
                <div class="form-row">
                    <div class="form-group"><label>商品コード</label><select name="code"><option value="">-- 選択 --</option><?php foreach ($products as $p): ?><option value="<?= htmlspecialchars($p['product_code']) ?>" <?= $code === $p['product_code'] ? 'selected' : '' ?>><?= htmlspecialchars($p['product_name']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group" style="flex:0;"><label>&nbsp;</label><button type="submit" class="btn btn-primary">検索</button></div>
                </div>
            </form>
        </div>
        <?php if ($product): ?>
        <div class="table-container">
            <h2 style="margin-bottom:16px;"><?= htmlspecialchars($product['product_name']) ?></h2>
            <table>
                <tr><th style="width:200px;">商品コード</th><td><?= htmlspecialchars($product['product_code']) ?></td></tr>
                <tr><th>商品名</th><td><?= htmlspecialchars($product['product_name']) ?></td></tr>
                <tr><th>カナ</th><td><?= htmlspecialchars($product['product_name_kana']) ?></td></tr>
                <tr><th>単位</th><td><?= htmlspecialchars($product['unit'] ?? '') ?></td></tr>
                <tr><th>入数</th><td><?= $product['case_quantity'] ?? 0 ?></td></tr>
                <tr><th>課税区分</th><td><?php $cats = ['','課税対象','非課税対象','課税対象外']; echo $cats[$product['tax_category']] ?? ''; ?></td></tr>
                <tr><th>軽減税率</th><td><?= $product['reduced_tax_flag'] ? '対象' : '' ?></td></tr>
                <tr><th>売上単価1（税抜）</th><td><?= number_format($product['selling_price1_excl'] ?? 0) ?></td></tr>
                <tr><th>売上単価1（税込）</th><td><?= number_format($product['selling_price1_incl'] ?? 0) ?></td></tr>
                <tr><th>売上原価（税抜）</th><td><?= number_format($product['cost_price_excl'] ?? 0) ?></td></tr>
            </table>
        </div>
        <?php elseif ($code): ?>
            <div class="alert alert-error">該当する商品が見つかりません。</div>
        <?php endif; ?>
    </main>
</body>
</html>
