<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';
require_once __DIR__ . '/../../Helpers/Csrf.php';
require_once __DIR__ . '/../../Models/BaseModel.php';
require_once __DIR__ . '/../../Models/SalesSlip.php';

Session::start();
Auth::requireLogin();
Auth::requireFiscalYear();

$salesModel = new SalesSlip();
$error = '';
$success = '';

// CSV出力処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'csv_output') {
        $conditions = [
            'customer_code' => $_POST['customer_code'] ?? '',
            'date_from' => $_POST['date_from'] ?? '',
            'date_to' => $_POST['date_to'] ?? '',
        ];

        $slips = $salesModel->search($conditions, 1, 99999);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="sales_output_' . date('YmdHis') . '.csv"');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM
        fputcsv($output, ['伝票番号', '売上日', '得意先コード', '得意先名', '税抜金額', '消費税', '税込金額', '状態']);

        foreach ($slips['data'] as $slip) {
            fputcsv($output, [
                $slip['sales_slip_no'],
                $slip['sales_date'],
                $slip['customer_code'],
                $slip['customer_name'] ?? '',
                $slip['total_amount'],
                $slip['tax_amount'],
                $slip['total_amount'] + $slip['tax_amount'],
                $slip['status'] == 0 ? '未請求' : '請求締済',
            ]);
        }

        fclose($output);
        exit;
    }
}

// 検索条件
$conditions = [];
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $conditions['customer_code'] = $_GET['customer_code'] ?? '';
    $conditions['date_from'] = $_GET['date_from'] ?? '';
    $conditions['date_to'] = $_GET['date_to'] ?? '';
}

$page = (int)($_GET['page'] ?? 1);
$slips = $salesModel->search($conditions, $page);

// 得意先一覧を取得
$db = Database::getConnection();
$stmt = $db->prepare("SELECT customer_code, customer_name FROM customers WHERE tenant_id = ? AND fiscal_year_id = ?");
$stmt->execute([Session::getTenantId(), Session::getFiscalYearId()]);
$customers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>売上伝票出力 - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/SalesManagementSystem/public/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <a href="/SalesManagementSystem/" style="font-size: 18px; color: #1e293b;"><?= APP_NAME ?></a>
            <span style="margin-left: 16px; color: #64748b;">売上伝票出力</span>
        </div>
        <div class="header-right">
            <span class="fiscal-year"><?= htmlspecialchars(Session::get('fiscal_year_label')) ?>年度</span>
            <a href="/SalesManagementSystem/" class="btn btn-small btn-secondary">TOP</a>
        </div>
    </header>

    <main style="padding: 24px; max-width: 1400px; margin: 0 auto;">
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="search-form">
            <form method="get" action="/SalesManagementSystem/sales/output.php">
                <div class="form-row">
                    <div class="form-group">
                        <label for="customer_code">得意先</label>
                        <select id="customer_code" name="customer_code">
                            <option value="">全て</option>
                            <?php foreach ($customers as $cust): ?>
                                <option value="<?= htmlspecialchars($cust['customer_code']) ?>" <?= ($conditions['customer_code'] ?? '') == $cust['customer_code'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cust['customer_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date_from">日付 From</label>
                        <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($conditions['date_from'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_to">日付 To</label>
                        <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($conditions['date_to'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="flex: 0;">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">検索</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h2>売上伝票一覧</h2>
                <form method="post" action="/SalesManagementSystem/sales/output.php" style="display: inline;">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="csv_output">
                    <input type="hidden" name="customer_code" value="<?= htmlspecialchars($conditions['customer_code'] ?? '') ?>">
                    <input type="hidden" name="date_from" value="<?= htmlspecialchars($conditions['date_from'] ?? '') ?>">
                    <input type="hidden" name="date_to" value="<?= htmlspecialchars($conditions['date_to'] ?? '') ?>">
                    <button type="submit" class="btn btn-success">CSV出力</button>
                </form>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>伝票番号</th>
                        <th>売上日</th>
                        <th>得意先</th>
                        <th class="text-right">税抜金額</th>
                        <th class="text-right">消費税</th>
                        <th class="text-right">税込合計</th>
                        <th>状態</th>
                        <th class="text-center">出力</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($slips['data'])): ?>
                    <tr><td colspan="8" class="text-center">データがありません。</td></tr>
                    <?php else: ?>
                    <?php foreach ($slips['data'] as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['sales_slip_no']) ?></td>
                        <td><?= htmlspecialchars($row['sales_date']) ?></td>
                        <td><?= htmlspecialchars($row['customer_name'] ?? $row['customer_code']) ?></td>
                        <td class="text-right"><?= number_format($row['total_amount']) ?></td>
                        <td class="text-right"><?= number_format($row['tax_amount']) ?></td>
                        <td class="text-right"><?= number_format($row['total_amount'] + $row['tax_amount']) ?></td>
                        <td><?= $row['status'] == 0 ? '未請求' : '請求締済' ?></td>
                        <td class="text-center">
                            <a href="/SalesManagementSystem/sales/input.php?action=edit&id=<?= $row['id'] ?>" class="btn btn-small btn-secondary">伝票</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($slips['total_pages'] > 1): ?>
            <div class="pagination">
                <?php for ($p = 1; $p <= $slips['total_pages']; $p++): ?>
                    <?php if ($p == $slips['page']): ?>
                        <span class="current"><?= $p ?></span>
                    <?php else: ?>
                        <a href="/SalesManagementSystem/sales/output.php?page=<?= $p ?>&customer_code=<?= urlencode($conditions['customer_code'] ?? '') ?>&date_from=<?= urlencode($conditions['date_from'] ?? '') ?>&date_to=<?= urlencode($conditions['date_to'] ?? '') ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
