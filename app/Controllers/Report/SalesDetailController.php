<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';

Session::start();
Auth::requireLogin();
Auth::requireFiscalYear();

$db = Database::getConnection();
$scope = [Session::getTenantId(), Session::getFiscalYearId()];

$customerCode = $_GET['customer_code'] ?? '';
$productCode = $_GET['product_code'] ?? '';
$staffCode = $_GET['staff_code'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$action = $_GET['action'] ?? 'list';

$where = "WHERE s.tenant_id = ? AND s.fiscal_year_id = ?";
$params = $scope;

if ($customerCode) { $where .= " AND s.customer_code = ?"; $params[] = $customerCode; }
if ($staffCode) { $where .= " AND s.staff_code = ?"; $params[] = $staffCode; }
if ($dateFrom) { $where .= " AND s.sales_date >= ?"; $params[] = $dateFrom; }
if ($dateTo) { $where .= " AND s.sales_date <= ?"; $params[] = $dateTo; }
if ($productCode) { $where .= " AND d.product_code = ?"; $params[] = $productCode; }

$sql = "SELECT s.sales_date, s.sales_slip_no, c.customer_name, st.staff_name, d.product_code, d.product_name, d.quantity, d.unit_price, d.amount, d.tax_rate
        FROM sales_slips s
        JOIN sales_details d ON s.id = d.sales_slip_id
        LEFT JOIN customers c ON s.customer_code = c.customer_code AND c.tenant_id = s.tenant_id AND c.fiscal_year_id = s.fiscal_year_id
        LEFT JOIN staff st ON s.staff_code = st.staff_code AND st.tenant_id = s.tenant_id AND st.fiscal_year_id = s.fiscal_year_id
        {$where} AND d.breakdown_type = 1
        ORDER BY s.sales_date, s.sales_slip_no, d.line_no";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$details = $stmt->fetchAll();

if ($action === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="sales_detail_' . date('YmdHis') . '.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['売上日', '伝票番号', '得意先', '担当者', '商品コード', '商品名', '数量', '単価', '金額']);
    foreach ($details as $d) {
        fputcsv($output, [$d['sales_date'], $d['sales_slip_no'], $d['customer_name'], $d['staff_name'], $d['product_code'], $d['product_name'], $d['quantity'], $d['unit_price'], $d['amount']]);
    }
    fclose($output);
    exit;
}

// 得意先・担当者・商品の一覧取得
$customers = $db->prepare("SELECT customer_code, customer_name FROM customers WHERE tenant_id = ? AND fiscal_year_id = ? ORDER BY customer_name_kana")->execute($scope) ? $db->prepare("SELECT customer_code, customer_name FROM customers WHERE tenant_id = ? AND fiscal_year_id = ? ORDER BY customer_name_kana") : null;
$stmt2 = $db->prepare("SELECT customer_code, customer_name FROM customers WHERE tenant_id = ? AND fiscal_year_id = ? ORDER BY customer_name_kana");
$stmt2->execute($scope);
$customerList = $stmt2->fetchAll();

$stmt3 = $db->prepare("SELECT staff_code, staff_name FROM staff WHERE tenant_id = ? AND fiscal_year_id = ?");
$stmt3->execute($scope);
$staffList = $stmt3->fetchAll();

$totalAmount = array_sum(array_column($details, 'amount'));
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><title>売上明細表 - 販売管理システム</title>
    <link rel="stylesheet" href="/SalesManagementSystem/public/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left"><a href="/SalesManagementSystem/" style="font-size:18px;color:#1e293b;">販売管理システム</a> <span style="margin-left:16px;color:#64748b;">売上明細表</span></div>
        <div class="header-right"><span class="fiscal-year"><?= htmlspecialchars(Session::get('fiscal_year_label')) ?>年度</span><a href="/SalesManagementSystem/" class="btn btn-small btn-secondary">TOP</a></div>
    </header>
    <main style="padding:24px;max-width:1400px;margin:0 auto;">
        <div class="search-form">
            <form method="get" action="/SalesManagementSystem/report/sales-detail.php">
                <div class="form-row">
                    <div class="form-group"><label>得意先</label><select name="customer_code"><option value="">全て</option><?php foreach ($customerList as $c): ?><option value="<?= htmlspecialchars($c['customer_code']) ?>" <?= $customerCode === $c['customer_code'] ? 'selected' : '' ?>><?= htmlspecialchars($c['customer_name']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>担当者</label><select name="staff_code"><option value="">全て</option><?php foreach ($staffList as $s): ?><option value="<?= htmlspecialchars($s['staff_code']) ?>" <?= $staffCode === $s['staff_code'] ? 'selected' : '' ?>><?= htmlspecialchars($s['staff_name']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>From</label><input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"></div>
                    <div class="form-group"><label>To</label><input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"></div>
                    <div class="form-group" style="flex:0;"><label>&nbsp;</label><button type="submit" class="btn btn-primary">検索</button></div>
                </div>
            </form>
        </div>
        <div class="table-container">
            <div class="table-header"><h2>売上明細表</h2><a href="?action=csv&customer_code=<?= urlencode($customerCode) ?>&staff_code=<?= urlencode($staffCode) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>" class="btn btn-success">CSV出力</a></div>
            <table>
                <thead><tr><th>売上日</th><th>伝票番号</th><th>得意先</th><th>担当者</th><th>商品コード</th><th>商品名</th><th class="text-right">数量</th><th class="text-right">単価</th><th class="text-right">金額</th></tr></thead>
                <tbody>
                <?php foreach ($details as $d): ?>
                <tr>
                    <td><?= htmlspecialchars($d['sales_date']) ?></td><td><?= htmlspecialchars($d['sales_slip_no']) ?></td>
                    <td><?= htmlspecialchars($d['customer_name']) ?></td><td><?= htmlspecialchars($d['staff_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($d['product_code']) ?></td><td><?= htmlspecialchars($d['product_name']) ?></td>
                    <td class="text-right"><?= number_format($d['quantity']) ?></td><td class="text-right"><?= number_format($d['unit_price']) ?></td>
                    <td class="text-right"><?= number_format($d['amount']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr style="font-weight:bold;border-top:2px solid #e2e8f0;"><td colspan="8">合計</td><td class="text-right"><?= number_format($totalAmount) ?></td></tr></tfoot>
            </table>
        </div>
    </main>
</body>
</html>
