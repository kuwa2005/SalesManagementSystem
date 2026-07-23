<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';
require_once __DIR__ . '/../../Helpers/Csrf.php';
require_once __DIR__ . '/../../Helpers/Validator.php';
require_once __DIR__ . '/../../Helpers/Numbering.php';
require_once __DIR__ . '/../../Models/BaseModel.php';
require_once __DIR__ . '/../../Models/Invoice.php';
require_once __DIR__ . '/../../Models/Customer.php';
require_once __DIR__ . '/../../Models/Staff.php';
require_once __DIR__ . '/../../Models/Department.php';

Session::start();
Auth::requireLogin();
Auth::requireFiscalYear();

$invoiceModel = new Invoice();
$customerModel = new Customer();
$action = $_GET['action'] ?? 'create';
$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

// 会社情報
$db = Database::getConnection();
$stmt = $db->prepare("SELECT * FROM company_info WHERE tenant_id = ? AND fiscal_year_id = ?");
$stmt->execute([Session::getTenantId(), Session::getFiscalYearId()]);
$companyInfo = $stmt->fetch();

// POST処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create') {
        $customerCode = trim($_POST['customer_code'] ?? '');
        $invoiceDate = trim($_POST['invoice_date'] ?? '');
        $followCutoffDay = isset($_POST['follow_cutoff_day']);
        $paymentTerm = trim($_POST['payment_term'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        if ($customerCode === '' || $invoiceDate === '') {
            $error = '得意先と締年月日を入力してください。';
        } else {
            try {
                // 対象売上の取得
                $unbilledSales = $invoiceModel->getUnbilledSales($customerCode, $invoiceDate, $followCutoffDay);

                if (empty($unbilledSales)) {
                    $error = '対象の未請求売上がありません。';
                } else {
                    // 合計計算
                    $totalExcl = 0;
                    $totalTax = 0;
                    $salesSlipIds = [];

                    foreach ($unbilledSales as $sales) {
                        $totalExcl += $sales['total_amount'];
                        $totalTax += $sales['tax_amount'];
                        $salesSlipIds[] = $sales['id'];
                    }

                    // 前回請求・入金の取得
                    $stmt = $db->prepare("
                        SELECT invoice_amount FROM invoices
                        WHERE tenant_id = ? AND fiscal_year_id = ? AND customer_code = ?
                        ORDER BY invoice_date DESC, id DESC LIMIT 1
                    ");
                    $stmt->execute([Session::getTenantId(), Session::getFiscalYearId(), $customerCode]);
                    $prevInvoice = $stmt->fetch();
                    $previousAmount = $prevInvoice ? $prevInvoice['invoice_amount'] : 0;

                    // 期間内の入金合計
                    $stmt = $db->prepare("
                        SELECT COALESCE(SUM(pd.amount), 0) FROM payment_details pd
                        JOIN payment_slips ps ON pd.payment_slip_id = ps.id
                        WHERE ps.tenant_id = ? AND ps.fiscal_year_id = ? AND ps.customer_code = ?
                        AND ps.payment_date <= ?
                    ");
                    $stmt->execute([Session::getTenantId(), Session::getFiscalYearId(), $customerCode, $invoiceDate]);
                    $paymentAmount = $stmt->fetchColumn();

                    $carryoverAmount = $previousAmount - $paymentAmount;
                    $totalAmount = $totalExcl + $totalTax;
                    $invoiceAmount = $carryoverAmount + $totalAmount;

                    // 請求書番号
                    $invoiceNo = Numbering::generateInvoiceNo(
                        Session::getTenantId(),
                        Session::getFiscalYearId(),
                        $companyInfo['invoice_numbering_method'] ?? 1
                    );

                    $invoiceData = [
                        'invoice_no' => $invoiceNo,
                        'invoice_date' => $invoiceDate,
                        'customer_code' => $customerCode,
                        'department_code' => trim($_POST['department_code'] ?? ''),
                        'staff_code' => trim($_POST['staff_code'] ?? ''),
                        'previous_amount' => $previousAmount,
                        'payment_amount' => $paymentAmount,
                        'carryover_amount' => $carryoverAmount,
                        'current_amount' => $totalExcl,
                        'tax_amount' => $totalTax,
                        'total_amount' => $totalAmount,
                        'invoice_amount' => $invoiceAmount,
                        'payment_term' => $paymentTerm,
                        'remarks' => $remarks,
                    ];

                    $invoiceId = $invoiceModel->createInvoice($invoiceData, $salesSlipIds);
                    $success = '請求書を作成しました。請求書番号: ' . htmlspecialchars($invoiceNo);
                    $action = 'view';
                    $id = $invoiceId;
                }
            } catch (Exception $e) {
                $error = '請求書作成に失敗しました: ' . $e->getMessage();
            }
        }
    }

    if ($postAction === 'release') {
        $targetId = (int)($_POST['id'] ?? 0);
        try {
            $invoiceModel->releaseInvoice($targetId);
            $success = '請求締を解除しました。';
            $action = 'list';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    if ($postAction === 'release_with_following') {
        $targetId = (int)($_POST['id'] ?? 0);
        try {
            $invoiceModel->releaseWithFollowing($targetId);
            $success = '請求締を解除しました（後続の請求書も含む）。';
            $action = 'list';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// 得意先一覧
$stmt = $db->prepare("SELECT customer_code, customer_name, billing_method FROM customers WHERE tenant_id = ? AND fiscal_year_id = ?");
$stmt->execute([Session::getTenantId(), Session::getFiscalYearId()]);
$customers = $stmt->fetchAll();

// 部門・担当者一覧
$stmt = $db->prepare("SELECT department_code, department_name_short FROM departments WHERE tenant_id = ? AND fiscal_year_id = ?");
$stmt->execute([Session::getTenantId(), Session::getFiscalYearId()]);
$departments = $stmt->fetchAll();

$stmt = $db->prepare("SELECT staff_code, staff_name FROM staff WHERE tenant_id = ? AND fiscal_year_id = ?");
$stmt->execute([Session::getTenantId(), Session::getFiscalYearId()]);
$staffList = $stmt->fetchAll();

// データ取得
$invoice = null;
$invoices = null;

if ($action === 'view' && $id) {
    $invoice = $invoiceModel->getWithDetails($id);
    if (!$invoice) {
        $action = 'create';
        $error = '請求書が見つかりません。';
    }
} elseif ($action === 'list') {
    $page = (int)($_GET['page'] ?? 1);
    $conditions = [
        'customer_code' => $_GET['customer_code'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'status' => $_GET['status'] ?? '',
    ];
    $invoices = $invoiceModel->search($conditions, $page);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>請求書作成 - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <a href="/top.php" style="font-size: 18px; color: #1e293b;"><?= APP_NAME ?></a>
            <span style="margin-left: 16px; color: #64748b;">請求書作成</span>
        </div>
        <div class="header-right">
            <span class="fiscal-year"><?= htmlspecialchars(Session::get('fiscal_year_label')) ?>年度</span>
            <a href="/top.php" class="btn btn-small btn-secondary">TOP</a>
        </div>
    </header>

    <main style="padding: 24px; max-width: 1200px; margin: 0 auto;">
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($action === 'create'): ?>
        <!-- 請求書作成フォーム -->
        <div class="slip-form">
            <h2 style="margin-bottom: 20px;">請求書作成</h2>

            <form method="post" action="/invoice/create.php">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="create">

                <div class="form-row">
                    <div class="form-group">
                        <label for="customer_code">得意先 <span style="color: red;">*</span></label>
                        <select id="customer_code" name="customer_code" required>
                            <option value="">-- 選択 --</option>
                            <?php foreach ($customers as $cust): ?>
                                <?php if ($cust['billing_method'] == 0): ?>
                                <option value="<?= htmlspecialchars($cust['customer_code']) ?>">
                                    <?= htmlspecialchars($cust['customer_name']) ?>
                                </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="invoice_date">締年月日 <span style="color: red;">*</span></label>
                        <input type="date" id="invoice_date" name="invoice_date" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="department_code">発行部門</label>
                        <select id="department_code" name="department_code">
                            <option value="">-- 選択 --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept['department_code']) ?>">
                                    <?= htmlspecialchars($dept['department_name_short']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="staff_code">担当者</label>
                        <select id="staff_code" name="staff_code">
                            <option value="">-- 選択 --</option>
                            <?php foreach ($staffList as $stf): ?>
                                <option value="<?= htmlspecialchars($stf['staff_code']) ?>">
                                    <?= htmlspecialchars($stf['staff_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="payment_term">支払期限</label>
                        <input type="text" id="payment_term" name="payment_term" maxlength="25"
                               value="<?= htmlspecialchars($companyInfo['payment_term'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="remarks">伝票摘要</label>
                        <input type="text" id="remarks" name="remarks" maxlength="80">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="follow_cutoff_day" checked>
                            締日指定に準拠する
                        </label>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">請求書を作成</button>
                </div>
            </form>
        </div>

        <?php elseif ($action === 'view' && $invoice): ?>
        <!-- 請求書表示 -->
        <div class="slip-form">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>請求書</h2>
                <div>
                    <a href="/invoice/create.php" class="btn btn-secondary">新規作成</a>
                    <a href="/invoice/create.php?action=list" class="btn btn-secondary">一覧</a>
                </div>
            </div>

            <div style="border: 1px solid #ccc; padding: 30px; background: white;">
                <div style="text-align: center; margin-bottom: 30px;">
                    <h1 style="font-size: 28px;"><?= htmlspecialchars($companyInfo['invoice_title'] ?? '請求書') ?></h1>
                </div>

                <div style="display: flex; justify-content: space-between; margin-bottom: 30px;">
                    <div>
                        <p>請求書番号: <?= htmlspecialchars($invoice['invoice_no']) ?></p>
                        <p>請求日: <?= htmlspecialchars($invoice['invoice_date']) ?></p>
                    </div>
                    <div style="text-align: right;">
                        <p><?= htmlspecialchars($companyInfo['company_name'] ?? '') ?></p>
                        <p><?= htmlspecialchars($companyInfo['address1'] ?? '') ?> <?= htmlspecialchars($companyInfo['address2'] ?? '') ?></p>
                        <p>TEL: <?= htmlspecialchars($companyInfo['tel'] ?? '') ?></p>
                    </div>
                </div>

                <div style="margin-bottom: 30px;">
                    <p style="font-size: 18px;"><?= htmlspecialchars($invoice['customer_name'] ?? '') ?> 御中</p>
                </div>

                <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
                    <tr style="background: #f1f5f9;">
                        <th style="padding: 10px; border: 1px solid #ccc; text-align: left;">項目</th>
                        <th style="padding: 10px; border: 1px solid #ccc; text-align: right;">金額</th>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ccc;">前回御請求額</td>
                        <td style="padding: 10px; border: 1px solid #ccc; text-align: right;"><?= number_format($invoice['previous_amount']) ?>円</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ccc;">御入金額</td>
                        <td style="padding: 10px; border: 1px solid #ccc; text-align: right;"><?= number_format($invoice['payment_amount']) ?>円</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ccc;">繰越金額</td>
                        <td style="padding: 10px; border: 1px solid #ccc; text-align: right;"><?= number_format($invoice['carryover_amount']) ?>円</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ccc;">今回御買上額</td>
                        <td style="padding: 10px; border: 1px solid #ccc; text-align: right;"><?= number_format($invoice['current_amount']) ?>円</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ccc;">消費税</td>
                        <td style="padding: 10px; border: 1px solid #ccc; text-align: right;"><?= number_format($invoice['tax_amount']) ?>円</td>
                    </tr>
                    <tr style="background: #f1f5f9; font-weight: bold;">
                        <td style="padding: 10px; border: 1px solid #ccc;">今回御請求額</td>
                        <td style="padding: 10px; border: 1px solid #ccc; text-align: right; font-size: 18px;"><?= number_format($invoice['invoice_amount']) ?>円</td>
                    </tr>
                </table>

                <?php if ($companyInfo['bank_info1']): ?>
                <div style="margin-bottom: 20px;">
                    <p>お振込先:</p>
                    <p><?= htmlspecialchars($companyInfo['bank_info1']) ?></p>
                    <?php if ($companyInfo['bank_info2']): ?>
                        <p><?= htmlspecialchars($companyInfo['bank_info2']) ?></p>
                    <?php endif; ?>
                    <?php if ($companyInfo['bank_info3']): ?>
                        <p><?= htmlspecialchars($companyInfo['bank_info3']) ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($invoice['payment_term']): ?>
                <div>
                    <p>お支払期限: <?= htmlspecialchars($invoice['payment_term']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php elseif ($action === 'list'): ?>
        <!-- 請求書一覧 -->
        <div class="search-form">
            <form method="get" action="/invoice/create.php">
                <input type="hidden" name="action" value="list">
                <div class="form-row">
                    <div class="form-group">
                        <label for="customer_code">得意先</label>
                        <select id="customer_code" name="customer_code">
                            <option value="">全て</option>
                            <?php foreach ($customers as $cust): ?>
                                <option value="<?= htmlspecialchars($cust['customer_code']) ?>" <?= ($_GET['customer_code'] ?? '') == $cust['customer_code'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cust['customer_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date_from">日付 From</label>
                        <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_to">日付 To</label>
                        <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
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
                <h2>請求書一覧</h2>
                <a href="/invoice/create.php" class="btn btn-primary">新規作成</a>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>請求書番号</th>
                        <th>請求日</th>
                        <th>得意先</th>
                        <th class="text-right">今回御請求額</th>
                        <th>状態</th>
                        <th class="text-center">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices['data'])): ?>
                    <tr><td colspan="6" class="text-center">データがありません。</td></tr>
                    <?php else: ?>
                    <?php foreach ($invoices['data'] as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['invoice_no']) ?></td>
                        <td><?= htmlspecialchars($row['invoice_date']) ?></td>
                        <td><?= htmlspecialchars($row['customer_name'] ?? $row['customer_code']) ?></td>
                        <td class="text-right"><?= number_format($row['invoice_amount']) ?></td>
                        <td>
                            <?php
                            $statusNames = [0 => '未請求', 1 => '請求締済', 2 => '入金紐付済', 3 => '締解除済'];
                            echo $statusNames[$row['status']] ?? '';
                            ?>
                        </td>
                        <td class="text-center">
                            <a href="/invoice/create.php?action=view&id=<?= $row['id'] ?>" class="btn btn-small btn-secondary">表示</a>
                            <?php if ($row['status'] == 1): ?>
                            <form method="post" action="/invoice/create.php" style="display: inline;" onsubmit="return confirm('この請求書の締を解除しますか？');">
                                <input type="hidden" name="action" value="release">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <button type="submit" class="btn btn-small btn-danger">解除</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($invoices['total_pages'] > 1): ?>
            <div class="pagination">
                <?php for ($p = 1; $p <= $invoices['total_pages']; $p++): ?>
                    <?php if ($p == $invoices['page']): ?>
                        <span class="current"><?= $p ?></span>
                    <?php else: ?>
                        <a href="/invoice/create.php?action=list&page=<?= $p ?>&customer_code=<?= urlencode($_GET['customer_code'] ?? '') ?>&date_from=<?= urlencode($_GET['date_from'] ?? '') ?>&date_to=<?= urlencode($_GET['date_to'] ?? '') ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
