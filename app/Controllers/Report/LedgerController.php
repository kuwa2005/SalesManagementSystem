<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';
require_once __DIR__ . '/../../Helpers/Csrf.php';

Session::start();
Auth::requireLogin();
Auth::requireFiscalYear();

$db = Database::getConnection();
$scope = [Session::getTenantId(), Session::getFiscalYearId()];

$customerCode = $_GET['customer_code'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$ledger = null;

if ($customerCode) {
    $where = "WHERE s.tenant_id = ? AND s.fiscal_year_id = ? AND s.customer_code = ?";
    $params = [$scope[0], $scope[1], $customerCode];

    if ($dateFrom) { $where .= " AND s.sales_date >= ?"; $params[] = $dateFrom; }
    if ($dateTo) { $where .= " AND s.sales_date <= ?"; $params[] = $dateTo; }

    // 売上
    $stmt = $db->prepare("SELECT s.sales_date, s.sales_slip_no, '売上' AS type, s.total_amount AS debit, 0 AS credit FROM sales_slips s {$where} ORDER BY s.sales_date");
    $stmt->execute($params);
    $sales = $stmt->fetchAll();

    // 入金
    $stmt = $db->prepare("SELECT p.payment_date AS sales_date, p.payment_slip_no AS sales_slip_no, '入金' AS type, 0 AS debit, p.total_amount AS credit FROM payment_slips p WHERE p.tenant_id = ? AND p.fiscal_year_id = ? AND p.customer_code = ? ORDER BY p.payment_date");
    $stmt->execute([$scope[0], $scope[1], $customerCode]);
    $payments = $stmt->fetchAll();

    // 合併してソート
    $ledger = array_merge($sales, $payments);
    usort($ledger, fn($a, $b) => strcmp($a['sales_date'], $b['sales_date']));

    // 得意先情報
    $stmt = $db->prepare("SELECT * FROM customers WHERE tenant_id = ? AND fiscal_year_id = ? AND customer_code = ?");
    $stmt->execute([$scope[0], $scope[1], $customerCode]);
    $customer = $stmt->fetch();
} else {
    $customer = null;
}

$stmt = $db->prepare("SELECT customer_code, customer_name FROM customers WHERE tenant_id = ? AND fiscal_year_id = ? ORDER BY customer_name_kana");
$stmt->execute($scope);
$customers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>得意先元帳 - 販売管理システム</title>
    <link rel="stylesheet" href="/SalesManagementSystem/public/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left"><a href="/SalesManagementSystem/" style="font-size:18px;color:#1e293b;">販売管理システム</a> <span style="margin-left:16px;color:#64748b;">得意先元帳</span></div>
        <div class="header-right"><span class="fiscal-year"><?= htmlspecialchars(Session::get('fiscal_year_label')) ?>年度</span><a href="/SalesManagementSystem/" class="btn btn-small btn-secondary">TOP</a></div>
    </header>
    <main style="padding:24px;max-width:1200px;margin:0 auto;">
        <div class="search-form">
            <form method="get" action="/SalesManagementSystem/report/ledger.php">
                <div class="form-row">
                    <div class="form-group">
                        <label>得意先</label>
                        <select name="customer_code"><option value="">-- 選択 --</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= htmlspecialchars($c['customer_code']) ?>" <?= $customerCode === $c['customer_code'] ? 'selected' : '' ?>><?= htmlspecialchars($c['customer_name']) ?></option>
                        <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>From</label><input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"></div>
                    <div class="form-group"><label>To</label><input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"></div>
                    <div class="form-group" style="flex:0;"><label>&nbsp;</label><button type="submit" class="btn btn-primary">検索</button></div>
                </div>
            </form>
        </div>
        <?php if ($ledger !== null): ?>
        <div class="table-container">
            <h2 style="margin-bottom:16px;"><?= htmlspecialchars($customer['customer_name'] ?? '') ?> 御得意先元帳</h2>
            <table>
                <thead><tr><th>日付</th><th>伝票番号</th><th>種別</th><th class="text-right">借方</th><th class="text-right">貸方</th></tr></thead>
                <tbody>
                <?php $balance = $customer['opening_accounts_receivable'] ?? 0; ?>
                <tr><td></td><td></td><td>期首残高</td><td class="text-right"><?= number_format($balance) ?></td><td></td></tr>
                <?php foreach ($ledger as $row): ?>
                    <?php $balance += $row['debit'] - $row['credit']; ?>
                    <tr><td><?= htmlspecialchars($row['sales_date']) ?></td><td><?= htmlspecialchars($row['sales_slip_no']) ?></td><td><?= htmlspecialchars($row['type']) ?></td><td class="text-right"><?= $row['debit'] ? number_format($row['debit']) : '' ?></td><td class="text-right"><?= $row['credit'] ? number_format($row['credit']) : '' ?></td></tr>
                <?php endforeach; ?>
                <tr style="font-weight:bold;border-top:2px solid #e2e8f0;"><td colspan="3">繰越残高</td><td></td><td class="text-right"><?= number_format($balance) ?></td></tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
