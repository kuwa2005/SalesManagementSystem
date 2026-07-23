<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';
require_once __DIR__ . '/../../Helpers/Csrf.php';
require_once __DIR__ . '/../../Helpers/Validator.php';
require_once __DIR__ . '/../../Helpers/Numbering.php';
require_once __DIR__ . '/../../Models/BaseModel.php';
require_once __DIR__ . '/../../Models/Payment.php';
require_once __DIR__ . '/../../Models/Invoice.php';
require_once __DIR__ . '/../../Models/PaymentType.php';
require_once __DIR__ . '/../../Models/Staff.php';

Session::start();
Auth::requireLogin();
Auth::requireFiscalYear();

$paymentModel = new Payment();
$invoiceModel = new Invoice();
$paymentTypeModel = new PaymentType();
$action = $_GET['action'] ?? 'input';
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

    if ($postAction === 'create' || $postAction === 'update') {
        $slipNo = trim($_POST['payment_slip_no'] ?? '');
        if ($slipNo === '' && $companyInfo && $companyInfo['slip_numbering_method'] != 3) {
            $slipNo = Numbering::generatePaymentSlipNo(
                Session::getTenantId(),
                Session::getFiscalYearId(),
                $companyInfo['slip_numbering_method']
            );
        }

        $header = [
            'payment_slip_no' => $slipNo,
            'payment_date' => trim($_POST['payment_date'] ?? ''),
            'staff_code' => trim($_POST['staff_code'] ?? ''),
            'customer_code' => trim($_POST['customer_code'] ?? ''),
            'remarks' => trim($_POST['remarks'] ?? ''),
            'print_detail_on_invoice' => isset($_POST['print_detail_on_invoice']) ? 1 : 0,
            'receipt_remarks' => trim($_POST['receipt_remarks'] ?? ''),
        ];

        // 明細データ
        $details = [];
        $lineNos = $_POST['detail_line_no'] ?? [];
        foreach ($lineNos as $i => $lineNo) {
            $paymentTypeCode = trim($_POST['detail_payment_type_code'][$i] ?? '');
            if ($paymentTypeCode === '') continue;

            $details[] = [
                'payment_type_code' => $paymentTypeCode,
                'amount' => (int)($_POST['detail_amount'][$i] ?? 0),
                'remarks' => trim($_POST['detail_remarks'][$i] ?? ''),
            ];
        }

        // 合計計算
        $totalAmount = 0;
        foreach ($details as $detail) {
            $totalAmount += $detail['amount'];
        }
        $header['total_amount'] = $totalAmount;

        // 請求書紐付
        $invoiceIds = $_POST['invoice_ids'] ?? [];

        // バリデーション
        $validator = new Validator($header);
        $validator->required('payment_slip_no', '伝票番号')
                  ->required('payment_date', '入金日')
                  ->date('payment_date', '入金日')
                  ->required('customer_code', '請求先');

        if ($validator->hasErrors()) {
            $error = $validator->getFirstError();
        } elseif (empty($details)) {
            $error = '明細を1行以上入力してください。';
        } else {
            try {
                if ($postAction === 'create') {
                    $paymentModel->createWithDetails($header, $details, $invoiceIds);
                    $success = '入金伝票を登録しました。';
                    $action = 'input';
                } else {
                    $paymentModel->update($id, $header);
                    $success = '入金伝票を更新しました。';
                    $action = 'edit';
                }
            } catch (Exception $e) {
                $error = '登録に失敗しました: ' . $e->getMessage();
            }
        }
    }

    if ($postAction === 'delete') {
        $targetId = (int)($_POST['id'] ?? 0);
        $paymentModel->deleteWithDetails($targetId);
        $success = '入金伝票を削除しました。';
        $action = 'input';
    }
}

// 得意先一覧
$stmt = $db->prepare("SELECT customer_code, customer_name FROM customers WHERE tenant_id = ? AND fiscal_year_id = ?");
$stmt->execute([Session::getTenantId(), Session::getFiscalYearId()]);
$customers = $stmt->fetchAll();

// 担当者一覧
$stmt = $db->prepare("SELECT staff_code, staff_name FROM staff WHERE tenant_id = ? AND fiscal_year_id = ?");
$stmt->execute([Session::getTenantId(), Session::getFiscalYearId()]);
$staffList = $stmt->fetchAll();

// 入金区分一覧
$paymentTypes = $paymentTypeModel->getAll();

// データ取得
$payment = null;
$payments = null;

if ($action === 'edit' && $id) {
    $payment = $paymentModel->getWithDetails($id);
    if (!$payment) {
        $action = 'input';
        $error = '入金伝票が見つかりません。';
    }
} elseif ($action === 'list') {
    $page = (int)($_GET['page'] ?? 1);
    $conditions = [
        'customer_code' => $_GET['customer_code'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
    ];
    $payments = $paymentModel->search($conditions, $page);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>入金入力 - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/css/style.css">
    <script>
    // 得意先選択時の未請求売上表示
    function onCustomerChange(code) {
        if (!code) return;

        fetch('/api/unbilled.php?customer_code=' + encodeURIComponent(code))
            .then(r => r.json())
            .then(data => {
                document.getElementById('unbilled_info').innerHTML =
                    '未請求売上: ' + data.count + '件 / ' + data.total_incl.toLocaleString() + '円';
            });
    }

    // 入金区分選択時の初期化
    function onPaymentTypeChange(index, code) {
        // 特に処理なし
    }

    // 合計計算
    function calculateTotal() {
        let total = 0;
        for (let i = 1; i <= 10; i++) {
            const amountEl = document.getElementById('detail_amount_' + i);
            if (amountEl) {
                total += parseInt(amountEl.value) || 0;
            }
        }
        document.getElementById('total_amount').textContent = total.toLocaleString();
    }
    </script>
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <a href="/top.php" style="font-size: 18px; color: #1e293b;"><?= APP_NAME ?></a>
            <span style="margin-left: 16px; color: #64748b;">入金入力</span>
        </div>
        <div class="header-right">
            <span class="fiscal-year"><?= htmlspecialchars(Session::get('fiscal_year_label')) ?>年度</span>
            <a href="/top.php" class="btn btn-small btn-secondary">TOP</a>
        </div>
    </header>

    <main style="padding: 24px; max-width: 1400px; margin: 0 auto;">
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($action === 'input' || $action === 'edit'): ?>
        <div class="slip-form">
            <h2 style="margin-bottom: 20px;"><?= $action === 'edit' ? '入金伝票訂正' : '入金入力' ?></h2>

            <form method="post" action="/payment/input.php">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update' : 'create' ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                <?php endif; ?>

                <div class="slip-header">
                    <div class="form-group">
                        <label for="payment_slip_no">伝票番号</label>
                        <input type="text" id="payment_slip_no" name="payment_slip_no" maxlength="10"
                               value="<?= htmlspecialchars($payment['payment_slip_no'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="payment_date">入金日 <span style="color: red;">*</span></label>
                        <input type="date" id="payment_date" name="payment_date"
                               value="<?= htmlspecialchars($payment['payment_date'] ?? date('Y-m-d')) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="staff_code">当社担当者</label>
                        <select id="staff_code" name="staff_code">
                            <option value="">-- 選択 --</option>
                            <?php foreach ($staffList as $stf): ?>
                                <option value="<?= htmlspecialchars($stf['staff_code']) ?>"
                                    <?= ($payment['staff_code'] ?? '') == $stf['staff_code'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($stf['staff_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="slip-header">
                    <div class="form-group">
                        <label for="customer_code">請求先 <span style="color: red;">*</span></label>
                        <select id="customer_code" name="customer_code" required onchange="onCustomerChange(this.value)">
                            <option value="">-- 選択 --</option>
                            <?php foreach ($customers as $cust): ?>
                                <option value="<?= htmlspecialchars($cust['customer_code']) ?>"
                                    <?= ($payment['customer_code'] ?? '') == $cust['customer_code'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cust['customer_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="unbilled_info" style="color: #64748b;"></div>
                </div>

                <h3 style="margin: 20px 0 12px;">入金明細</h3>

                <div class="detail-table">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 40px;">No</th>
                                <th style="width: 150px;">入金区分</th>
                                <th style="width: 120px;">金額</th>
                                <th style="width: 200px;">備考</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                            <?php
                            $detail = $payment['details'][$i-1] ?? null;
                            $detailPaymentTypeCode = $detail['payment_type_code'] ?? '';
                            $detailAmount = $detail['amount'] ?? 0;
                            $detailRemarks = $detail['remarks'] ?? '';
                            ?>
                            <tr>
                                <td><?= $i ?></td>
                                <td>
                                    <input type="hidden" name="detail_line_no[]" value="<?= $i ?>">
                                    <select name="detail_payment_type_code[]" style="width: 100%;">
                                        <option value="">-- 選択 --</option>
                                        <?php foreach ($paymentTypes as $pt): ?>
                                            <option value="<?= htmlspecialchars($pt['payment_type_code']) ?>"
                                                <?= $detailPaymentTypeCode == $pt['payment_type_code'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($pt['payment_type_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="detail_amount[]" id="detail_amount_<?= $i ?>"
                                           value="<?= $detailAmount ?>" style="width: 100%;"
                                           onchange="calculateTotal()">
                                </td>
                                <td>
                                    <input type="text" name="detail_remarks[]" value="<?= htmlspecialchars($detailRemarks) ?>" style="width: 100%;">
                                </td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>

                <div class="slip-total">
                    <div class="total-item">
                        <div class="total-label">合計</div>
                        <div class="total-value" id="total_amount"><?= number_format($payment['total_amount'] ?? 0) ?></div>
                    </div>
                </div>

                <div class="form-row" style="margin-top: 20px;">
                    <div class="form-group">
                        <label for="remarks">伝票摘要</label>
                        <input type="text" id="remarks" name="remarks" maxlength="40"
                               value="<?= htmlspecialchars($payment['remarks'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="print_detail_on_invoice" value="1"
                                   <?= ($payment['print_detail_on_invoice'] ?? 0) ? 'checked' : '' ?>>
                            明細備考を請求書に印字する
                        </label>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">登録</button>
                    <?php if ($action === 'edit'): ?>
                        <a href="/payment/input.php" class="btn btn-secondary">新規入力へ</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($action === 'edit'): ?>
            <div style="display: flex; gap: 10px; margin-top: 20px; justify-content: center;">
                <form method="post" action="/payment/input.php" style="display: inline;" onsubmit="return confirm('この伝票を削除しますか？');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button type="submit" class="btn btn-danger">削除</button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <?php elseif ($action === 'list'): ?>
        <!-- 入金伝票一覧 -->
        <div class="search-form">
            <form method="get" action="/payment/input.php">
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
                <h2>入金伝票一覧</h2>
                <a href="/payment/input.php" class="btn btn-primary">新規登録</a>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>伝票番号</th>
                        <th>入金日</th>
                        <th>得意先</th>
                        <th class="text-right">入金額</th>
                        <th class="text-center">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments['data'])): ?>
                    <tr><td colspan="5" class="text-center">データがありません。</td></tr>
                    <?php else: ?>
                    <?php foreach ($payments['data'] as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['payment_slip_no']) ?></td>
                        <td><?= htmlspecialchars($row['payment_date']) ?></td>
                        <td><?= htmlspecialchars($row['customer_name'] ?? $row['customer_code']) ?></td>
                        <td class="text-right"><?= number_format($row['total_amount']) ?></td>
                        <td class="text-center">
                            <a href="/payment/input.php?action=edit&id=<?= $row['id'] ?>" class="btn btn-small btn-secondary">編集</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($payments['total_pages'] > 1): ?>
            <div class="pagination">
                <?php for ($p = 1; $p <= $payments['total_pages']; $p++): ?>
                    <?php if ($p == $payments['page']): ?>
                        <span class="current"><?= $p ?></span>
                    <?php else: ?>
                        <a href="/payment/input.php?action=list&page=<?= $p ?>&customer_code=<?= urlencode($_GET['customer_code'] ?? '') ?>&date_from=<?= urlencode($_GET['date_from'] ?? '') ?>&date_to=<?= urlencode($_GET['date_to'] ?? '') ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
