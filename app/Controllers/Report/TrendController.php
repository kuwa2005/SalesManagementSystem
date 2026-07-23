<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';

Session::start(); Auth::requireLogin(); Auth::requireFiscalYear();
$db = Database::getConnection(); $scope = [Session::getTenantId(), Session::getFiscalYearId()];
$action = $_GET['action'] ?? 'list';

// 月別売上推移（今年vs前年）
$stmt = $db->prepare("
    SELECT DATE_FORMAT(s.sales_date, '%Y-%m') AS month, SUM(d.amount) AS total
    FROM sales_slips s JOIN sales_details d ON s.id = d.sales_slip_id
    WHERE s.tenant_id = ? AND s.fiscal_year_id = ? AND d.breakdown_type IN (1,2,3)
    GROUP BY month ORDER BY month
");
$stmt->execute($scope); $thisYear = $stmt->fetchAll();

$thisYearData = [];
foreach ($thisYear as $row) { $thisYearData[$row['month']] = $row['total']; }
$months = array_keys($thisYearData);
$grandTotal = array_sum($thisYearData);

if ($action === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="trend_' . date('YmdHis') . '.csv"');
    $out = fopen('php://output', 'w'); fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['年月', '売上金額', '構成比']);
    foreach ($thisYearData as $m => $t) { fputcsv($out, [$m, $t, $grandTotal > 0 ? round($t / $grandTotal * 100, 1) : 0]); }
    fclose($out); exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head><meta charset="UTF-8"><title>売上推移表 - 販売管理システム</title><link rel="stylesheet" href="/SalesManagementSystem/public/css/style.css"></head>
<body>
    <header class="main-header">
        <div class="header-left"><a href="/SalesManagementSystem/" style="font-size:18px;color:#1e293b;">販売管理システム</a> <span style="margin-left:16px;color:#64748b;">売上推移表</span></div>
        <div class="header-right"><span class="fiscal-year"><?= htmlspecialchars(Session::get('fiscal_year_label')) ?>年度</span><a href="/SalesManagementSystem/" class="btn btn-small btn-secondary">TOP</a></div>
    </header>
    <main style="padding:24px;max-width:1200px;margin:0 auto;">
        <div class="table-container">
            <div class="table-header"><h2>売上推移表</h2><a href="?action=csv" class="btn btn-success">CSV出力</a></div>
            <table><thead><tr><th>年月</th><th class="text-right">売上金額</th><th class="text-right">構成比</th></tr></thead><tbody>
            <?php foreach ($thisYearData as $m => $t): ?>
            <tr><td><?= htmlspecialchars($m) ?></td><td class="text-right"><?= number_format($t) ?></td><td class="text-right"><?= $grandTotal > 0 ? round($t / $grandTotal * 100, 1) : 0 ?>%</td></tr>
            <?php endforeach; ?>
            </tbody><tfoot><tr style="font-weight:bold;border-top:2px solid #e2e8f0;"><td>合計</td><td class="text-right"><?= number_format($grandTotal) ?></td><td class="text-right">100%</td></tr></tfoot></table>
        </div>
    </main>
</body>
</html>
