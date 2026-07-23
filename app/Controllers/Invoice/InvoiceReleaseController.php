<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';
require_once __DIR__ . '/../../Helpers/Csrf.php';
require_once __DIR__ . '/../../Models/Invoice.php';

Session::start();
Auth::requireLogin();
Auth::requireFiscalYear();

$invoiceModel = new Invoice();
$db = Database::getConnection();
$scope = [Session::getTenantId(), Session::getFiscalYearId()];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $targetId = (int)($_POST['id'] ?? 0);
    try {
        $invoiceModel->releaseWithFollowing($targetId);
        $success = '請求締を解除しました（後続の請求書も含む）。';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$stmt = $db->prepare("SELECT i.*, c.customer_name FROM invoices i LEFT JOIN customers c ON i.customer_code = c.customer_code AND c.tenant_id = i.tenant_id AND c.fiscal_year_id = i.fiscal_year_id WHERE i.tenant_id = ? AND i.fiscal_year_id = ? AND i.status = 1 ORDER BY i.invoice_date DESC");
$stmt->execute($scope);
$invoices = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><title>請求締解除 - 販売管理システム</title>
    <link rel="stylesheet" href="/SalesManagementSystem/public/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left"><a href="/SalesManagementSystem/" style="font-size:18px;color:#1e293b;">販売管理システム</a> <span style="margin-left:16px;color:#64748b;">請求締解除</span></div>
        <div class="header-right"><span class="fiscal-year"><?= htmlspecialchars(Session::get('fiscal_year_label')) ?>年度</span><a href="/SalesManagementSystem/" class="btn btn-small btn-secondary">TOP</a></div>
    </header>
    <main style="padding:24px;max-width:1200px;margin:0 auto;">
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <div class="table-container">
            <h2 style="margin-bottom:16px;">請求締解除一覧</h2>
            <p style="margin-bottom:16px;color:#64748b;">指定した請求書より後の日付の請求締済み請求書も同時に解除されます。</p>
            <table><thead><tr><th>請求書番号</th><th>請求日</th><th>得意先</th><th class="text-right">請求額</th><th class="text-center">操作</th></tr></thead><tbody>
            <?php if (empty($invoices)): ?><tr><td colspan="5" class="text-center">解除対象の請求書がありません。</td></tr>
            <?php else: foreach ($invoices as $inv): ?>
            <tr>
                <td><?= htmlspecialchars($inv['invoice_no']) ?></td><td><?= htmlspecialchars($inv['invoice_date']) ?></td>
                <td><?= htmlspecialchars($inv['customer_name'] ?? $inv['customer_code']) ?></td><td class="text-right"><?= number_format($inv['invoice_amount']) ?></td>
                <td class="text-center"><form method="post" style="display:inline;" onsubmit="return confirm('この請求書以降の全ての請求締を解除しますか？');"><input type="hidden" name="id" value="<?= $inv['id'] ?>"><button type="submit" class="btn btn-small btn-danger">解除</button></form></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody></table>
        </div>
    </main>
</body>
</html>
