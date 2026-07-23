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

$stmt = $db->prepare("
    SELECT c.customer_code, c.customer_name, c.opening_accounts_receivable,
    COALESCE((SELECT SUM(s.total_amount) FROM sales_slips s WHERE s.tenant_id = c.tenant_id AND s.fiscal_year_id = c.fiscal_year_id AND s.customer_code = c.customer_code), 0) AS total_sales,
    COALESCE((SELECT SUM(p.total_amount) FROM payment_slips p WHERE p.tenant_id = c.tenant_id AND p.fiscal_year_id = c.fiscal_year_id AND p.customer_code = c.customer_code), 0) AS total_payment
    FROM customers c
    WHERE c.tenant_id = ? AND c.fiscal_year_id = ?
    ORDER BY c.customer_name_kana
");
$stmt->execute($scope);
$balances = $stmt->fetchAll();

if ($action === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="balance_' . date('YmdHis') . '.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['得意先コード', '得意先名', '期首残高', '売上合計', '入金合計', '売掛残高']);
    foreach ($balances as $b) {
        $balance = $b['opening_accounts_receivable'] + $b['total_sales'] - $b['total_payment'];
        fputcsv($output, [$b['customer_code'], $b['customer_name'], $b['opening_accounts_receivable'], $b['total_sales'], $b['total_payment'], $balance]);
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><title>売掛残高一覧 - 販売管理システム</title>
    <link rel="stylesheet" href="/SalesManagementSystem/public/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left"><a href="/SalesManagementSystem/" style="font-size:18px;color:#1e293b;">販売管理システム</a> <span style="margin-left:16px;color:#64748b;">売掛残高一覧</span></div>
        <div class="header-right"><span class="fiscal-year"><?= htmlspecialchars(Session::get('fiscal_year_label')) ?>年度</span><a href="/SalesManagementSystem/" class="btn btn-small btn-secondary">TOP</a></div>
    </header>
    <main style="padding:24px;max-width:1200px;margin:0 auto;">
        <div class="table-container">
            <div class="table-header">
                <h2>売掛残高一覧</h2>
                <a href="?action=csv" class="btn btn-success">CSV出力</a>
            </div>
            <table>
                <thead><tr><th>コード</th><th>得意先名</th><th class="text-right">期首残高</th><th class="text-right">売上合計</th><th class="text-right">入金合計</th><th class="text-right">売掛残高</th></tr></thead>
                <tbody>
                <?php foreach ($balances as $b): ?>
                <?php $balance = $b['opening_accounts_receivable'] + $b['total_sales'] - $b['total_payment']; ?>
                <tr>
                    <td><?= htmlspecialchars($b['customer_code']) ?></td>
                    <td><?= htmlspecialchars($b['customer_name']) ?></td>
                    <td class="text-right"><?= number_format($b['opening_accounts_receivable']) ?></td>
                    <td class="text-right"><?= number_format($b['total_sales']) ?></td>
                    <td class="text-right"><?= number_format($b['total_payment']) ?></td>
                    <td class="text-right" style="font-weight:bold;"><?= number_format($balance) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
