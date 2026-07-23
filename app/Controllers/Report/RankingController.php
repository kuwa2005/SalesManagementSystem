<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';

Session::start(); Auth::requireLogin(); Auth::requireFiscalYear();
$db = Database::getConnection(); $scope = [Session::getTenantId(), Session::getFiscalYearId()];
$action = $_GET['action'] ?? 'list';

$stmt = $db->prepare("
    SELECT c.customer_name, SUM(d.amount) AS total
    FROM sales_slips s JOIN sales_details d ON s.id = d.sales_slip_id
    LEFT JOIN customers c ON s.customer_code = c.customer_code AND c.tenant_id = s.tenant_id AND c.fiscal_year_id = s.fiscal_year_id
    WHERE s.tenant_id = ? AND s.fiscal_year_id = ? AND d.breakdown_type IN (1,2,3)
    GROUP BY s.customer_code ORDER BY total DESC
");
$stmt->execute($scope); $data = $stmt->fetchAll();
$grandTotal = array_sum(array_column($data, 'total'));

if ($action === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="ranking_' . date('YmdHis') . '.csv"');
    $out = fopen('php://output', 'w'); fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['順位', '得意先', '売上金額', '構成比', '累計']);
    $cumulative = 0; $rank = 1;
    foreach ($data as $d) { $cumulative += $d['total']; fputcsv($out, [$rank++, $d['customer_name'], $d['total'], $grandTotal > 0 ? round($d['total']/$grandTotal*100,1) : 0, $cumulative]); }
    fclose($out); exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head><meta charset="UTF-8"><title>売上順位表 - 販売管理システム</title><link rel="stylesheet" href="/SalesManagementSystem/public/css/style.css"></head>
<body>
    <header class="main-header">
        <div class="header-left"><a href="/SalesManagementSystem/" style="font-size:18px;color:#1e293b;">販売管理システム</a> <span style="margin-left:16px;color:#64748b;">売上順位表</span></div>
        <div class="header-right"><span class="fiscal-year"><?= htmlspecialchars(Session::get('fiscal_year_label')) ?>年度</span><a href="/SalesManagementSystem/" class="btn btn-small btn-secondary">TOP</a></div>
    </header>
    <main style="padding:24px;max-width:1200px;margin:0 auto;">
        <div class="table-container">
            <div class="table-header"><h2>売上順位表</h2><a href="?action=csv" class="btn btn-success">CSV出力</a></div>
            <table><thead><tr><th>順位</th><th>得意先</th><th class="text-right">売上金額</th><th class="text-right">構成比</th><th class="text-right">累計</th></tr></thead><tbody>
            <?php $cumulative = 0; $rank = 1; foreach ($data as $d): $cumulative += $d['total']; ?>
            <tr><td><?= $rank++ ?></td><td><?= htmlspecialchars($d['customer_name']) ?></td><td class="text-right"><?= number_format($d['total']) ?></td><td class="text-right"><?= $grandTotal > 0 ? round($d['total']/$grandTotal*100,1) : 0 ?>%</td><td class="text-right"><?= number_format($cumulative) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
    </main>
</body>
</html>
