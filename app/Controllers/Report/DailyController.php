<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';

Session::start(); Auth::requireLogin(); Auth::requireFiscalYear();
$db = Database::getConnection(); $scope = [Session::getTenantId(), Session::getFiscalYearId()];
$dateFrom = $_GET['date_from'] ?? ''; $dateTo = $_GET['date_to'] ?? '';
$action = $_GET['action'] ?? 'list';

$where = "WHERE s.tenant_id = ? AND s.fiscal_year_id = ? AND d.breakdown_type IN (1,2,3)"; $params = $scope;
if ($dateFrom) { $where .= " AND s.sales_date >= ?"; $params[] = $dateFrom; }
if ($dateTo) { $where .= " AND s.sales_date <= ?"; $params[] = $dateTo; }

$stmt = $db->prepare("SELECT s.sales_date, SUM(d.amount) AS total, COUNT(DISTINCT s.id) AS cnt FROM sales_slips s JOIN sales_details d ON s.id = d.sales_slip_id {$where} GROUP BY s.sales_date ORDER BY s.sales_date");
$stmt->execute($params); $data = $stmt->fetchAll();

if ($action === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="daily_' . date('YmdHis') . '.csv"');
    $out = fopen('php://output', 'w'); fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['日付', '件数', '売上金額']);
    foreach ($data as $d) { fputcsv($out, [$d['sales_date'], $d['cnt'], $d['total']]); }
    fclose($out); exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head><meta charset="UTF-8"><title>売上日報 - 販売管理システム</title><link rel="stylesheet" href="/SalesManagementSystem/public/css/style.css"></head>
<body>
    <header class="main-header">
        <div class="header-left"><a href="/SalesManagementSystem/" style="font-size:18px;color:#1e293b;">販売管理システム</a> <span style="margin-left:16px;color:#64748b;">売上日報</span></div>
        <div class="header-right"><span class="fiscal-year"><?= htmlspecialchars(Session::get('fiscal_year_label')) ?>年度</span><a href="/SalesManagementSystem/" class="btn btn-small btn-secondary">TOP</a></div>
    </header>
    <main style="padding:24px;max-width:1200px;margin:0 auto;">
        <div class="search-form"><form method="get"><div class="form-row">
            <div class="form-group"><label>From</label><input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"></div>
            <div class="form-group"><label>To</label><input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"></div>
            <div class="form-group" style="flex:0;"><label>&nbsp;</label><button type="submit" class="btn btn-primary">検索</button></div>
        </div></form></div>
        <div class="table-container">
            <div class="table-header"><h2>売上日報</h2><a href="?action=csv&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>" class="btn btn-success">CSV出力</a></div>
            <table><thead><tr><th>日付</th><th class="text-right">件数</th><th class="text-right">売上金額</th></tr></thead><tbody>
            <?php foreach ($data as $d): ?><tr><td><?= htmlspecialchars($d['sales_date']) ?></td><td class="text-right"><?= $d['cnt'] ?></td><td class="text-right"><?= number_format($d['total']) ?></td></tr><?php endforeach; ?>
            </tbody></table>
        </div>
    </main>
</body>
</html>
