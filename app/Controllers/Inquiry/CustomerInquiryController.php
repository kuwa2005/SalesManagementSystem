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
$customer = null;

if ($code) {
    $stmt = $db->prepare("SELECT * FROM customers WHERE tenant_id = ? AND fiscal_year_id = ? AND customer_code = ?");
    $stmt->execute([$scope[0], $scope[1], $code]);
    $customer = $stmt->fetch();
}

$stmt = $db->prepare("SELECT customer_code, customer_name FROM customers WHERE tenant_id = ? AND fiscal_year_id = ? ORDER BY customer_name_kana");
$stmt->execute($scope);
$customers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><title>得意先マスタ照会 - 販売管理システム</title>
    <link rel="stylesheet" href="/SalesManagementSystem/public/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left"><a href="/SalesManagementSystem/" style="font-size:18px;color:#1e293b;">販売管理システム</a> <span style="margin-left:16px;color:#64748b;">得意先マスタ照会</span></div>
        <div class="header-right"><span class="fiscal-year"><?= htmlspecialchars(Session::get('fiscal_year_label')) ?>年度</span><a href="/SalesManagementSystem/" class="btn btn-small btn-secondary">TOP</a></div>
    </header>
    <main style="padding:24px;max-width:900px;margin:0 auto;">
        <div class="search-form">
            <form method="get">
                <div class="form-row">
                    <div class="form-group"><label>得意先コード</label><select name="code"><option value="">-- 選択 --</option><?php foreach ($customers as $c): ?><option value="<?= htmlspecialchars($c['customer_code']) ?>" <?= $code === $c['customer_code'] ? 'selected' : '' ?>><?= htmlspecialchars($c['customer_name']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group" style="flex:0;"><label>&nbsp;</label><button type="submit" class="btn btn-primary">検索</button></div>
                </div>
            </form>
        </div>
        <?php if ($customer): ?>
        <div class="table-container">
            <h2 style="margin-bottom:16px;"><?= htmlspecialchars($customer['customer_name']) ?></h2>
            <table>
                <tr><th style="width:200px;">得意先コード</th><td><?= htmlspecialchars($customer['customer_code']) ?></td></tr>
                <tr><th>得意先名</th><td><?= htmlspecialchars($customer['customer_name']) ?></td></tr>
                <tr><th>カナ</th><td><?= htmlspecialchars($customer['customer_name_kana']) ?></td></tr>
                <tr><th>敬称</th><td><?= htmlspecialchars($customer['customer_honorific']) ?></td></tr>
                <tr><th>郵便番号</th><td><?= htmlspecialchars($customer['postal_code']) ?></td></tr>
                <tr><th>住所</th><td><?= htmlspecialchars($customer['address1'] . ' ' . $customer['address2'] . ' ' . ($customer['address3'] ?? '')) ?></td></tr>
                <tr><th>TEL</th><td><?= htmlspecialchars($customer['tel'] ?? '') ?></td></tr>
                <tr><th>FAX</th><td><?= htmlspecialchars($customer['fax'] ?? '') ?></td></tr>
                <tr><th>メール</th><td><?= htmlspecialchars($customer['email'] ?? '') ?></td></tr>
                <tr><th>担当者</th><td><?= htmlspecialchars($customer['customer_staff_name'] ?? '') ?></td></tr>
                <tr><th>税処理</th><td><?php $taxTypes = ['','外税/伝票計','外税/請求時','内税/伝票計','内税/請求時','免税','外税/手入力']; echo $taxTypes[$customer['tax_processing']] ?? ''; ?></td></tr>
                <tr><th>単価種別</th><td><?= '売上単価' . $customer['price_type'] ?></td></tr>
                <tr><th>請求方法</th><td><?= $customer['billing_method'] == 0 ? '締め請求' : '都度請求' ?></td></tr>
                <tr><th>期首売掛残高</th><td><?= number_format($customer['opening_accounts_receivable'] ?? 0) ?></td></tr>
            </table>
        </div>
        <?php elseif ($code): ?>
            <div class="alert alert-error">該当する得意先が見つかりません。</div>
        <?php endif; ?>
    </main>
</body>
</html>
