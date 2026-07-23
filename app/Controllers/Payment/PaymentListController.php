<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';

Session::start();
Auth::requireLogin();
Auth::requireFiscalYear();

$db = Database::getConnection();
$scope = [Session::getTenantId(), Session::getFiscalYearId()];
$action = $_GET['action'] ?? 'list';

$customerFrom = $_GET['customer_from'] ?? '';
$customerTo = $_GET['customer_to'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$where = "WHERE p.tenant_id = ? AND p.fiscal_year_id = ?";
$params = $scope;
if ($customerFrom) { $where .= " AND p.customer_code >= ?"; $params[] = $customerFrom; }
if ($customerTo) { $where .= " AND p.customer_code <= ?"; $params[] = $customerTo; }
if ($dateFrom) { $where .= " AND p.payment_date >= ?"; $params[] = $dateFrom; }
if ($dateTo) { $where .= " AND p.payment_date <= ?"; $params[] = $dateTo; }

$stmt = $db->prepare("SELECT p.*, c.customer_name FROM payment_slips p LEFT JOIN customers c ON p.customer_code = c.customer_code AND c.tenant_id = p.tenant_id AND c.fiscal_year_id = p.fiscal_year_id {$where} ORDER BY p.payment_date DESC");
$stmt->execute($params);
$payments = $stmt->fetchAll();

if ($action === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="payment_list_' . date('YmdHis') . '.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['入金日', '伝票番号', '得意先コード', '得意先名', '入金額']);
    foreach ($payments as $p) { fputcsv($output, [$p['payment_date'], $p['payment_slip_no'], $p['customer_code'], $p['customer_name'] ?? '', $p['total_amount']]); }
    fclose($output);
    exit;
}

$customers = $db->prepare("SELECT customer_code, customer_name FROM customers WHERE tenant_id = ? AND fiscal_year_id = ? ORDER BY customer_code");
$customers->execute($scope);
$customerList = $customers->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><title>入金実績一覧 - 販売管理システム</title>
    <link rel="stylesheet" href="/SalesManagementSystem/public/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left"><a href="/SalesManagementSystem/" style="font-size:18px;color:#1e293b;">販売管理システム</a> <span style="margin-left:16px;color:#64748b;">入金実績一覧</span></div>
        <div class="header-right"><span class="fiscal-year"><?= htmlspecialchars(Session::get('fiscal_year_label')) ?>年度</span><a href="/SalesManagementSystem/" class="btn btn-small btn-secondary">TOP</a></div>
    </header>
    <main style="padding:24px;max-width:1200px;margin:0 auto;">
        <div class="search-form">
            <form method="get"><div class="form-row">
                <div class="form-group"><label>得意先From</label><select name="customer_from"><option value="">指定なし</option><?php foreach ($customerList as $c): ?><option value="<?= htmlspecialchars($c['customer_code']) ?>"><?= htmlspecialchars($c['customer_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>得意先To</label><select name="customer_to"><option value="">指定なし</option><?php foreach ($customerList as $c): ?><option value="<?= htmlspecialchars($c['customer_code']) ?>"><?= htmlspecialchars($c['customer_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>From</label><input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"></div>
                <div class="form-group"><label>To</label><input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"></div>
                <div class="form-group" style="flex:0;"><label>&nbsp;</label><button type="submit" class="btn btn-primary">検索</button></div>
            </div></form>
        </div>
        <div class="table-container">
            <div class="table-header"><h2>入金実績一覧</h2><a href="?action=csv&customer_from=<?= urlencode($customerFrom) ?>&customer_to=<?= urlencode($customerTo) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>" class="btn btn-success">CSV出力</a></div>
            <table><thead><tr><th>入金日</th><th>伝票番号</th><th>得意先</th><th class="text-right">入金額</th></tr></thead><tbody>
            <?php foreach ($payments as $p): ?>
            <tr><td><?= htmlspecialchars($p['payment_date']) ?></td><td><?= htmlspecialchars($p['payment_slip_no']) ?></td><td><?= htmlspecialchars($p['customer_name'] ?? $p['customer_code']) ?></td><td class="text-right"><?= number_format($p['total_amount']) ?></td></tr>
            <?php endforeach; if (empty($payments)): ?><tr><td colspan="4" class="text-center">データがありません。</td></tr><?php endif; ?>
            </tbody></table>
        </div>
    </main>
</body>
</html>
