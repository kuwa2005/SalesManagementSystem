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

$customerFrom = $_GET['customer_from'] ?? '';
$customerTo = $_GET['customer_to'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$exportType = $_GET['export_type'] ?? '1';

// 得意先一覧
$customers = $db->prepare("SELECT customer_code, customer_name FROM customers WHERE tenant_id = ? AND fiscal_year_id = ? ORDER BY customer_code");
$customers->execute($scope);
$customerList = $customers->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();

    $customerFrom = $_POST['customer_from'] ?? '';
    $customerTo = $_POST['customer_to'] ?? '';
    $dateFrom = $_POST['date_from'] ?? '';
    $dateTo = $_POST['date_to'] ?? '';
    $exportType = $_POST['export_type'] ?? '1';

    $where = "WHERE s.tenant_id = ? AND s.fiscal_year_id = ?";
    $params = $scope;

    if ($customerFrom) { $where .= " AND s.customer_code >= ?"; $params[] = $customerFrom; }
    if ($customerTo) { $where .= " AND s.customer_code <= ?"; $params[] = $customerTo; }
    if ($dateFrom) { $where .= " AND s.sales_date >= ?"; $params[] = $dateFrom; }
    if ($dateTo) { $where .= " AND s.sales_date <= ?"; $params[] = $dateTo; }

    if ($exportType === '1') {
        $where .= " AND s.status = 1";
    }

    $sql = "SELECT s.sales_date, s.sales_slip_no, c.customer_name, d.product_name, d.amount, d.tax_rate, st.staff_name
            FROM sales_slips s
            JOIN sales_details d ON s.id = d.sales_slip_id
            LEFT JOIN customers c ON s.customer_code = c.customer_code AND c.tenant_id = s.tenant_id AND c.fiscal_year_id = s.fiscal_year_id
            LEFT JOIN staff st ON s.staff_code = st.staff_code AND st.tenant_id = s.tenant_id AND st.fiscal_year_id = s.fiscal_year_id
            {$where} AND d.breakdown_type IN (1, 2, 3)
            ORDER BY s.sales_date, s.sales_slip_no";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    if (empty($data)) {
        echo '<script>alert("出力対象データがありません。");history.back();</script>';
        exit;
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="accounting_' . date('YmdHis') . '.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['日付', '伝票番号', '得意先', '商品', '金額', '税率', '担当者', '科目コード']);

    foreach ($data as $row) {
        $code = '4000';
        if ($row['amount'] < 0) {
            if (abs($row['amount']) > 0) {
                // 返品判定は内訳区分で行うべきだが、ここでは金額で判定
                $code = '8000';
            }
        }
        fputcsv($output, [$row['sales_date'], $row['sales_slip_no'], $row['customer_name'], $row['product_name'], $row['amount'], $row['tax_rate'], $row['staff_name'], $code]);
    }

    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><title>会計データ出力 - 販売管理システム</title>
    <link rel="stylesheet" href="/SalesManagementSystem/public/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left"><a href="/SalesManagementSystem/" style="font-size:18px;color:#1e293b;">販売管理システム</a> <span style="margin-left:16px;color:#64748b;">会計データ出力</span></div>
        <div class="header-right"><span class="fiscal-year"><?= htmlspecialchars(Session::get('fiscal_year_label')) ?>年度</span><a href="/SalesManagementSystem/" class="btn btn-small btn-secondary">TOP</a></div>
    </header>
    <main style="padding:24px;max-width:900px;margin:0 auto;">
        <div class="slip-form">
            <h2 style="margin-bottom:20px;">会計データ出力</h2>
            <form method="post" action="/SalesManagementSystem/external/accounting.php">
                <?= Csrf::field() ?>
                <div class="form-row">
                    <div class="form-group"><label>得意先From</label><select name="customer_from"><option value="">指定なし</option><?php foreach ($customerList as $c): ?><option value="<?= htmlspecialchars($c['customer_code']) ?>"><?= htmlspecialchars($c['customer_name']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>得意先To</label><select name="customer_to"><option value="">指定なし</option><?php foreach ($customerList as $c): ?><option value="<?= htmlspecialchars($c['customer_code']) ?>"><?= htmlspecialchars($c['customer_name']) ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>日付From</label><input type="date" name="date_from"></div>
                    <div class="form-group"><label>日付To</label><input type="date" name="date_to"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>集計区分</label>
                        <select name="export_type">
                            <option value="1">伝票単位（請求締済データ）</option>
                            <option value="2">伝票単位（全データ）</option>
                        </select>
                    </div>
                </div>
                <div style="text-align:center;margin-top:24px;">
                    <button type="submit" class="btn btn-primary">CSV出力</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
