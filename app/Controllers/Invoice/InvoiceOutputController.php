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
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$where = "WHERE i.tenant_id = ? AND i.fiscal_year_id = ? AND i.status = 1";
$params = $scope;
if ($customerCode) { $where .= " AND i.customer_code = ?"; $params[] = $customerCode; }
if ($dateFrom) { $where .= " AND i.invoice_date >= ?"; $params[] = $dateFrom; }
if ($dateTo) { $where .= " AND i.invoice_date <= ?"; $params[] = $dateTo; }

$stmt = $db->prepare("SELECT i.*, c.customer_name FROM invoices i LEFT JOIN customers c ON i.customer_code = c.customer_code AND c.tenant_id = i.tenant_id AND c.fiscal_year_id = i.fiscal_year_id {$where} ORDER BY i.invoice_date DESC");
$stmt->execute($params);
$invoices = $stmt->fetchAll();

$customers = $db->prepare("SELECT customer_code, customer_name FROM customers WHERE tenant_id = ? AND fiscal_year_id = ? ORDER BY customer_name_kana");
$customers->execute($scope);
$customerList = $customers->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><title>請求書再出力 - 販売管理システム</title>
    <link rel="stylesheet" href="/SalesManagementSystem/public/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left"><a href="/SalesManagementSystem/" style="font-size:18px;color:#1e293b;">販売管理システム</a> <span style="margin-left:16px;color:#64748b;">請求書再出力</span></div>
        <div class="header-right"><span class="fiscal-year"><?= htmlspecialchars(Session::get('fiscal_year_label')) ?>年度</span><a href="/SalesManagementSystem/" class="btn btn-small btn-secondary">TOP</a></div>
    </header>
    <main style="padding:24px;max-width:1200px;margin:0 auto;">
        <div class="search-form">
            <form method="get"><div class="form-row">
                <div class="form-group"><label>得意先</label><select name="customer_code"><option value="">全て</option><?php foreach ($customerList as $c): ?><option value="<?= htmlspecialchars($c['customer_code']) ?>" <?= $customerCode === $c['customer_code'] ? 'selected' : '' ?>><?= htmlspecialchars($c['customer_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>From</label><input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"></div>
                <div class="form-group"><label>To</label><input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"></div>
                <div class="form-group" style="flex:0;"><label>&nbsp;</label><button type="submit" class="btn btn-primary">検索</button></div>
            </div></form>
        </div>
        <div class="table-container">
            <h2 style="margin-bottom:16px;">請求書一覧（再出力対象）</h2>
            <table><thead><tr><th>請求書番号</th><th>請求日</th><th>得意先</th><th class="text-right">請求額</th><th class="text-center">操作</th></tr></thead><tbody>
            <?php if (empty($invoices)): ?><tr><td colspan="5" class="text-center">データがありません。</td></tr>
            <?php else: foreach ($invoices as $inv): ?>
            <tr><td><?= htmlspecialchars($inv['invoice_no']) ?></td><td><?= htmlspecialchars($inv['invoice_date']) ?></td><td><?= htmlspecialchars($inv['customer_name'] ?? $inv['customer_code']) ?></td><td class="text-right"><?= number_format($inv['invoice_amount']) ?></td><td class="text-center"><a href="/SalesManagementSystem/invoice/create.php?action=view&id=<?= $inv['id'] ?>" class="btn btn-small btn-secondary">表示</a></td></tr>
            <?php endforeach; endif; ?>
            </tbody></table>
        </div>
    </main>
</body>
</html>
