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

$where = "WHERE pil.id IS NOT NULL AND p.tenant_id = ? AND p.fiscal_year_id = ?";
$params = $scope;
if ($customerCode) { $where .= " AND p.customer_code = ?"; $params[] = $customerCode; }
if ($dateFrom) { $where .= " AND p.payment_date >= ?"; $params[] = $dateFrom; }
if ($dateTo) { $where .= " AND p.payment_date <= ?"; $params[] = $dateTo; }

$stmt = $db->prepare("SELECT p.*, c.customer_name, i.invoice_no, i.invoice_amount FROM payment_slips p LEFT JOIN customers c ON p.customer_code = c.customer_code AND c.tenant_id = p.tenant_id AND c.fiscal_year_id = p.fiscal_year_id LEFT JOIN payment_invoice_links pil ON p.id = pil.payment_slip_id LEFT JOIN invoices i ON pil.invoice_id = i.id {$where} ORDER BY p.payment_date DESC");
$stmt->execute($params);
$payments = $stmt->fetchAll();

$customers = $db->prepare("SELECT customer_code, customer_name FROM customers WHERE tenant_id = ? AND fiscal_year_id = ? ORDER BY customer_name_kana");
$customers->execute($scope);
$customerList = $customers->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><title>領収書出力 - 販売管理システム</title>
    <link rel="stylesheet" href="/SalesManagementSystem/public/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left"><a href="/SalesManagementSystem/" style="font-size:18px;color:#1e293b;">販売管理システム</a> <span style="margin-left:16px;color:#64748b;">領収書出力</span></div>
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
            <h2 style="margin-bottom:16px;">領収書出力対象（請求書紐付済み入金）</h2>
            <table><thead><tr><th>入金日</th><th>伝票番号</th><th>得意先</th><th>請求書番号</th><th class="text-right">入金額</th></tr></thead><tbody>
            <?php if (empty($payments)): ?><tr><td colspan="5" class="text-center">データがありません。</td></tr>
            <?php else: foreach ($payments as $p): ?>
            <tr><td><?= htmlspecialchars($p['payment_date']) ?></td><td><?= htmlspecialchars($p['payment_slip_no']) ?></td><td><?= htmlspecialchars($p['customer_name'] ?? $p['customer_code']) ?></td><td><?= htmlspecialchars($p['invoice_no'] ?? '') ?></td><td class="text-right"><?= number_format($p['total_amount']) ?></td></tr>
            <?php endforeach; endif; ?>
            </tbody></table>
        </div>
    </main>
</body>
</html>
