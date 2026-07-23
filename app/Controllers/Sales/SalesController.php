<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';
require_once __DIR__ . '/../../Helpers/Csrf.php';
require_once __DIR__ . '/../../Helpers/Validator.php';
require_once __DIR__ . '/../../Helpers/Numbering.php';
require_once __DIR__ . '/../../Helpers/TaxCalculator.php';
require_once __DIR__ . '/../../Models/BaseModel.php';
require_once __DIR__ . '/../../Models/SalesSlip.php';
require_once __DIR__ . '/../../Models/Customer.php';
require_once __DIR__ . '/../../Models/Product.php';
require_once __DIR__ . '/../../Models/Staff.php';
require_once __DIR__ . '/../../Models/Department.php';

Session::start();
Auth::requireLogin();
Auth::requireFiscalYear();

$salesModel = new SalesSlip();
$customerModel = new Customer();
$productModel = new Product();
$staffModel = new Staff();
$deptModel = new Department();

$action = $_GET['action'] ?? 'input';
$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

// 伝票番号取得用の会社情報
$db = Database::getConnection();
$stmt = $db->prepare("SELECT * FROM company_info WHERE tenant_id = ? AND fiscal_year_id = ?");
$stmt->execute([Session::getTenantId(), Session::getFiscalYearId()]);
$companyInfo = $stmt->fetch();

$staffList = $staffModel->getAll();
$deptList = $deptModel->getAll();

// POST処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create' || $postAction === 'update') {
        // ヘッダデータ
        $slipNo = trim($_POST['sales_slip_no'] ?? '');
        if ($slipNo === '' && $companyInfo && $companyInfo['slip_numbering_method'] != 3) {
            $slipNo = Numbering::generateSalesSlipNo(
                Session::getTenantId(),
                Session::getFiscalYearId(),
                $companyInfo['slip_numbering_method']
            );
        }

        $header = [
            'sales_slip_no' => $slipNo,
            'sales_date' => trim($_POST['sales_date'] ?? ''),
            'department_code' => trim($_POST['department_code'] ?? ''),
            'staff_code' => trim($_POST['staff_code'] ?? ''),
            'customer_code' => trim($_POST['customer_code'] ?? ''),
            'delivery_customer_code' => trim($_POST['delivery_customer_code'] ?? trim($_POST['customer_code'] ?? '')),
            'tax_processing' => (int)($_POST['tax_processing'] ?? 1),
            'price_type' => (int)($_POST['price_type'] ?? 1),
            'remarks' => trim($_POST['remarks'] ?? ''),
            'staff_print' => isset($_POST['staff_print']) ? 1 : 0,
            'invoice_remarks_print' => isset($_POST['invoice_remarks_print']) ? 1 : 0,
        ];

        // 明細データ
        $details = [];
        $lineNos = $_POST['detail_line_no'] ?? [];
        foreach ($lineNos as $i => $lineNo) {
            if (trim($_POST['detail_product_code'][$i] ?? '') === '' && trim($_POST['detail_breakdown_name'][$i] ?? '') === '') {
                continue; // 空行はスキップ
            }

            $details[] = [
                'breakdown_type' => (int)($_POST['detail_breakdown_type'][$i] ?? 1),
                'product_code' => trim($_POST['detail_product_code'][$i] ?? ''),
                'product_name' => trim($_POST['detail_product_name'][$i] ?? ''),
                'unit' => trim($_POST['detail_unit'][$i] ?? ''),
                'case_quantity' => (int)($_POST['detail_case'][$i] ?? 0),
                'quantity' => (float)($_POST['detail_quantity'][$i] ?? 0),
                'cost_price' => (int)($_POST['detail_cost_price'][$i] ?? 0),
                'unit_price' => (int)($_POST['detail_unit_price'][$i] ?? 0),
                'amount' => (int)($_POST['detail_amount'][$i] ?? 0),
                'tax_rate' => (float)($_POST['detail_tax_rate'][$i] ?? 0),
                'remarks' => trim($_POST['detail_remarks'][$i] ?? ''),
            ];
        }

        // 合計計算
        $totalAmount = 0;
        $totalTax = 0;
        $totalGrossProfit = 0;
        foreach ($details as $detail) {
            if ($detail['breakdown_type'] == 1 || $detail['breakdown_type'] == 2) {
                $totalAmount += $detail['amount'];
                $totalTax += TaxCalculator::calculateTax($detail['amount'], $detail['tax_rate'], $header['tax_processing']);
                $totalGrossProfit += ($detail['amount'] - $detail['cost_price'] * $detail['quantity']);
            } elseif ($detail['breakdown_type'] == 3) {
                $totalAmount += $detail['amount'];
                $totalTax += TaxCalculator::calculateTax($detail['amount'], $detail['tax_rate'], $header['tax_processing']);
            } elseif ($detail['breakdown_type'] == 4) {
                $totalTax = $detail['amount'];
            }
        }

        $header['total_amount'] = $totalAmount;
        $header['tax_amount'] = $totalTax;
        $header['gross_profit'] = $totalGrossProfit;

        // バリデーション
        $validator = new Validator($header);
        $validator->required('sales_slip_no', '伝票番号')
                  ->required('sales_date', '売上日付')
                  ->date('sales_date', '売上日付')
                  ->required('customer_code', '請求先')
                  ->required('delivery_customer_code', '納品先');

        if ($validator->hasErrors()) {
            $error = $validator->getFirstError();
        } elseif (empty($details)) {
            $error = '明細を1行以上入力してください。';
        } else {
            try {
                if ($postAction === 'create') {
                    $salesModel->createWithDetails($header, $details);
                    $success = '売上伝票を登録しました。伝票番号: ' . htmlspecialchars($header['sales_slip_no']);
                    $action = 'input';
                    $id = 0;
                } else {
                    $existing = $salesModel->findById($id);
                    if ($existing && !$salesModel->isUnbilled($id)) {
                        $error = '請求締済みの伝票は訂正できません。';
                    } else {
                        $salesModel->updateWithDetails($id, $header, $details);
                        $success = '売上伝票を更新しました。';
                        $action = 'edit';
                    }
                }
            } catch (Exception $e) {
                $error = '登録に失敗しました: ' . $e->getMessage();
            }
        }
    }

    if ($postAction === 'delete') {
        $targetId = (int)($_POST['id'] ?? 0);
        if (!$salesModel->isUnbilled($targetId)) {
            $error = '請求締済みの伝票は削除できません。';
        } else {
            $salesModel->deleteWithDetails($targetId);
            $success = '売上伝票を削除しました。';
            $action = 'input';
        }
    }

    if ($postAction === 'copy') {
        $sourceId = (int)($_POST['id'] ?? 0);
        try {
            $newSlipNo = Numbering::generateSalesSlipNo(
                Session::getTenantId(),
                Session::getFiscalYearId(),
                $companyInfo['slip_numbering_method'] ?? 1
            );
            $newId = $salesModel->copy($sourceId, $newSlipNo);
            $success = '伝票を複写しました。新しい伝票番号: ' . htmlspecialchars($newSlipNo);
            $id = $newId;
            $action = 'edit';
        } catch (Exception $e) {
            $error = '複写に失敗しました。';
        }
    }

    if ($postAction === 'red_slip') {
        $sourceId = (int)($_POST['id'] ?? 0);
        try {
            $newSlipNo = Numbering::generateSalesSlipNo(
                Session::getTenantId(),
                Session::getFiscalYearId(),
                $companyInfo['slip_numbering_method'] ?? 1
            );
            $newId = $salesModel->createRedSlip($sourceId, $newSlipNo);
            $success = '赤伝を登録しました。新しい伝票番号: ' . htmlspecialchars($newSlipNo);
            $id = $newId;
            $action = 'edit';
        } catch (Exception $e) {
            $error = '赤伝登録に失敗しました。';
        }
    }
}

// データ取得
$slip = null;
if ($action === 'edit' && $id) {
    $slip = $salesModel->getWithDetails($id);
    if (!$slip) {
        $action = 'input';
        $error = '伝票が見つかりません。';
    }
}

// 売上伝票出力用の処理
if ($action === 'output') {
    $outputAction = $_GET['output_action'] ?? 'list';
    if ($outputAction === 'generate') {
        // PDF生成処理（ここでは簡易的なCSV出力）
        // 実際にはTCPDF等を使用してPDF生成
    }
}

// 得意先一覧を取得
$db = Database::getConnection();
$stmt = $db->prepare("SELECT customer_code, customer_name, tax_processing, price_type, billing_method FROM customers WHERE tenant_id = ? AND fiscal_year_id = ?");
$stmt->execute([Session::getTenantId(), Session::getFiscalYearId()]);
$customers = $stmt->fetchAll();

// 商品一覧を取得
$stmt = $db->prepare("SELECT product_code, product_name, unit, case_quantity, selling_price1_excl, cost_price_excl, tax_category, reduced_tax_flag FROM products WHERE tenant_id = ? AND fiscal_year_id = ?");
$stmt->execute([Session::getTenantId(), Session::getFiscalYearId()]);
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>売上伝票入力 - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/css/style.css">
    <script>
    // 得意先選択時の自動設定
    function onCustomerChange(code) {
        const customers = <?= json_encode($customers) ?>;
        const customer = customers.find(c => c.customer_code === code);
        if (customer) {
            document.getElementById('tax_processing').value = customer.tax_processing;
            document.getElementById('price_type').value = customer.price_type;
            document.getElementById('delivery_customer_code').value = code;
        }
    }

    // 商品選択時の自動設定
    function onProductSelect(lineIndex, code) {
        const products = <?= json_encode($products) ?>;
        const product = products.find(p => p.product_code === code);
        if (product) {
            document.getElementById('detail_product_name_' + lineIndex).value = product.product_name;
            document.getElementById('detail_unit_' + lineIndex).value = product.unit || '';
            document.getElementById('detail_case_quantity_' + lineIndex).value = product.case_quantity || 0;

            const priceType = document.getElementById('price_type').value;
            let unitPrice = 0;
            switch(priceType) {
                case '1': unitPrice = product.selling_price1_excl || 0; break;
                case '2': unitPrice = product.selling_price2_excl || 0; break;
                case '3': unitPrice = product.selling_price3_excl || 0; break;
                case '4': unitPrice = product.selling_price4_excl || 0; break;
                case '5': unitPrice = product.cost_price_excl || 0; break;
            }
            document.getElementById('detail_unit_price_' + lineIndex).value = unitPrice;
            document.getElementById('detail_cost_price_' + lineIndex).value = product.cost_price_excl || 0;

            // 税率設定
            let taxRate = 10;
            if (product.tax_category == 2 || product.tax_category == 3) {
                taxRate = 0;
            } else if (product.reduced_tax_flag) {
                taxRate = 8;
            }
            document.getElementById('detail_tax_rate_' + lineIndex).value = taxRate;

            calculateLine(lineIndex);
        }
    }

    // 行計算
    function calculateLine(index) {
        const quantity = parseFloat(document.getElementById('detail_quantity_' + index).value) || 0;
        const unitPrice = parseInt(document.getElementById('detail_unit_price_' + index).value) || 0;
        const amount = Math.floor(quantity * unitPrice);
        document.getElementById('detail_amount_' + index).value = amount;
        calculateTotal();
    }

    // ケース数変更時の数量計算
    function onCaseChange(index) {
        const caseQty = parseInt(document.getElementById('detail_case_' + index).value) || 0;
        const caseQuantity = parseInt(document.getElementById('detail_case_quantity_' + index).value) || 0;
        if (caseQty > 0 && caseQuantity > 0) {
            document.getElementById('detail_quantity_' + index).value = caseQty * caseQuantity;
            calculateLine(index);
        }
    }

    // 合計計算
    function calculateTotal() {
        let totalAmount = 0;
        let totalTax = 0;
        const taxProcessing = document.getElementById('tax_processing').value;

        for (let i = 1; i <= 20; i++) {
            const amountEl = document.getElementById('detail_amount_' + i);
            const taxRateEl = document.getElementById('detail_tax_rate_' + i);
            const breakdownEl = document.getElementById('detail_breakdown_type_' + i);

            if (!amountEl) continue;

            const amount = parseInt(amountEl.value) || 0;
            const taxRate = parseFloat(taxRateEl.value) || 0;
            const breakdown = parseInt(breakdownEl.value) || 1;

            if (breakdown == 1 || breakdown == 2 || breakdown == 3) {
                totalAmount += amount;
                totalTax += Math.floor(amount * taxRate / 100);
            } else if (breakdown == 4) {
                totalTax = amount;
            }
        }

        document.getElementById('total_amount').textContent = totalAmount.toLocaleString();
        document.getElementById('total_tax').textContent = totalTax.toLocaleString();
        document.getElementById('total_incl').textContent = (totalAmount + totalTax).toLocaleString();
    }

    // 明細行追加
    let lineCount = <?= count($slip['details'] ?? []) ?: 1 ?>;
    function addLine() {
        lineCount++;
        // 実際には行を動的に追加
    }
    </script>
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <a href="/top.php" style="font-size: 18px; color: #1e293b;"><?= APP_NAME ?></a>
            <span style="margin-left: 16px; color: #64748b;">売上伝票入力</span>
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
            <h2 style="margin-bottom: 20px;"><?= $action === 'edit' ? '売上伝票訂正' : '売上伝票入力' ?></h2>

            <form method="post" action="/sales/input.php" id="salesForm">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update' : 'create' ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                <?php endif; ?>

                <div class="slip-header">
                    <div class="form-group">
                        <label for="sales_slip_no">伝票番号</label>
                        <input type="text" id="sales_slip_no" name="sales_slip_no" maxlength="10"
                               value="<?= htmlspecialchars($slip['sales_slip_no'] ?? ($companyInfo['slip_numbering_method'] == 3 ? '' : '自動')) ?>"
                               <?= ($companyInfo['slip_numbering_method'] ?? 1) != 3 ? 'readonly style="background: #f1f5f9;"' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label for="sales_date">売上日付 <span style="color: red;">*</span></label>
                        <input type="date" id="sales_date" name="sales_date"
                               value="<?= htmlspecialchars($slip['sales_date'] ?? date('Y-m-d')) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="department_code">部門</label>
                        <select id="department_code" name="department_code">
                            <option value="">-- 選択 --</option>
                            <?php foreach ($deptList as $dept): ?>
                                <option value="<?= htmlspecialchars($dept['department_code']) ?>"
                                    <?= ($slip['department_code'] ?? '') == $dept['department_code'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['department_name_short']) ?>
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
                                    <?= ($slip['customer_code'] ?? '') == $cust['customer_code'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cust['customer_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="delivery_customer_code">納品先 <span style="color: red;">*</span></label>
                        <select id="delivery_customer_code" name="delivery_customer_code" required>
                            <option value="">-- 選択 --</option>
                            <?php foreach ($customers as $cust): ?>
                                <option value="<?= htmlspecialchars($cust['customer_code']) ?>"
                                    <?= ($slip['delivery_customer_code'] ?? '') == $cust['customer_code'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cust['customer_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="staff_code">当社担当者</label>
                        <select id="staff_code" name="staff_code">
                            <option value="">-- 選択 --</option>
                            <?php foreach ($staffList as $stf): ?>
                                <option value="<?= htmlspecialchars($stf['staff_code']) ?>"
                                    <?= ($slip['staff_code'] ?? '') == $stf['staff_code'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($stf['staff_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="slip-header">
                    <div class="form-group">
                        <label for="tax_processing">消費税</label>
                        <select id="tax_processing" name="tax_processing">
                            <option value="1" <?= ($slip['tax_processing'] ?? 1) == 1 ? 'selected' : '' ?>>外税/伝票計</option>
                            <option value="2" <?= ($slip['tax_processing'] ?? 1) == 2 ? 'selected' : '' ?>>外税/請求時</option>
                            <option value="3" <?= ($slip['tax_processing'] ?? 1) == 3 ? 'selected' : '' ?>>内税/伝票計</option>
                            <option value="4" <?= ($slip['tax_processing'] ?? 1) == 4 ? 'selected' : '' ?>>内税/請求時</option>
                            <option value="5" <?= ($slip['tax_processing'] ?? 1) == 5 ? 'selected' : '' ?>>免税</option>
                            <option value="6" <?= ($slip['tax_processing'] ?? 1) == 6 ? 'selected' : '' ?>>外税/手入力</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="price_type">単価種別</label>
                        <select id="price_type" name="price_type">
                            <option value="1" <?= ($slip['price_type'] ?? 1) == 1 ? 'selected' : '' ?>>売上単価1</option>
                            <option value="2" <?= ($slip['price_type'] ?? 1) == 2 ? 'selected' : '' ?>>売上単価2</option>
                            <option value="3" <?= ($slip['price_type'] ?? 1) == 3 ? 'selected' : '' ?>>売上単価3</option>
                            <option value="4" <?= ($slip['price_type'] ?? 1) == 4 ? 'selected' : '' ?>>売上単価4</option>
                            <option value="5" <?= ($slip['price_type'] ?? 1) == 5 ? 'selected' : '' ?>>売上原価</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="remarks">伝票摘要</label>
                        <input type="text" id="remarks" name="remarks" maxlength="40"
                               value="<?= htmlspecialchars($slip['remarks'] ?? '') ?>">
                    </div>
                </div>

                <h3 style="margin: 20px 0 12px;">明細</h3>

                <div class="detail-table">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 40px;">No</th>
                                <th style="width: 100px;">内訳区分</th>
                                <th style="width: 120px;">商品コード</th>
                                <th style="width: 200px;">商品名/摘要</th>
                                <th style="width: 60px;">単位</th>
                                <th style="width: 60px;">ケース</th>
                                <th style="width: 80px;">数量</th>
                                <th style="width: 100px;">原単価</th>
                                <th style="width: 100px;">単価</th>
                                <th style="width: 120px;">金額</th>
                                <th style="width: 60px;">税率</th>
                                <th style="width: 120px;">備考</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 1; $i <= 20; $i++): ?>
                            <?php
                            $detail = $slip['details'][$i-1] ?? null;
                            $detailBreakdownType = $detail['breakdown_type'] ?? ($i == 1 ? 1 : 1);
                            $detailProductCode = $detail['product_code'] ?? '';
                            $detailProductName = $detail['product_name'] ?? '';
                            $detailUnit = $detail['unit'] ?? '';
                            $detailCaseQty = $detail['case_quantity'] ?? 0;
                            $detailQuantity = $detail['quantity'] ?? 0;
                            $detailCostPrice = $detail['cost_price'] ?? 0;
                            $detailUnitPrice = $detail['unit_price'] ?? 0;
                            $detailAmount = $detail['amount'] ?? 0;
                            $detailTaxRate = $detail['tax_rate'] ?? 10;
                            $detailRemarks = $detail['remarks'] ?? '';
                            ?>
                            <tr>
                                <td><?= $i ?></td>
                                <td>
                                    <input type="hidden" name="detail_line_no[]" value="<?= $i ?>">
                                    <select name="detail_breakdown_type[]" id="detail_breakdown_type_<?= $i ?>" style="width: 100%;">
                                        <option value="1" <?= $detailBreakdownType == 1 ? 'selected' : '' ?>>通常</option>
                                        <option value="2" <?= $detailBreakdownType == 2 ? 'selected' : '' ?>>値引き</option>
                                        <option value="3" <?= $detailBreakdownType == 3 ? 'selected' : '' ?>>返品</option>
                                        <option value="4" <?= $detailBreakdownType == 4 ? 'selected' : '' ?>>消費税</option>
                                        <option value="5" <?= $detailBreakdownType == 5 ? 'selected' : '' ?>>摘要</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="detail_product_code[]" id="detail_product_code_<?= $i ?>"
                                           value="<?= htmlspecialchars($detailProductCode) ?>"
                                           onchange="onProductSelect(<?= $i ?>, this.value)"
                                           style="width: 100%;" list="product_list_<?= $i ?>">
                                    <datalist id="product_list_<?= $i ?>">
                                        <?php foreach ($products as $p): ?>
                                            <option value="<?= htmlspecialchars($p['product_code']) ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </td>
                                <td>
                                    <input type="text" name="detail_product_name[]" id="detail_product_name_<?= $i ?>"
                                           value="<?= htmlspecialchars($detailProductName) ?>" style="width: 100%;">
                                </td>
                                <td>
                                    <input type="text" name="detail_unit[]" id="detail_unit_<?= $i ?>"
                                           value="<?= htmlspecialchars($detailUnit) ?>" style="width: 100%;">
                                    <input type="hidden" name="detail_case_quantity[]" id="detail_case_quantity_<?= $i ?>"
                                           value="<?= $detailCaseQty ?>">
                                </td>
                                <td>
                                    <input type="number" name="detail_case[]" id="detail_case_<?= $i ?>"
                                           value="<?= $detailCaseQty ?>" style="width: 100%;"
                                           onchange="onCaseChange(<?= $i ?>)">
                                </td>
                                <td>
                                    <input type="number" name="detail_quantity[]" id="detail_quantity_<?= $i ?>"
                                           value="<?= $detailQuantity ?>" style="width: 100%;"
                                           onchange="calculateLine(<?= $i ?>)">
                                </td>
                                <td>
                                    <input type="number" name="detail_cost_price[]" id="detail_cost_price_<?= $i ?>"
                                           value="<?= $detailCostPrice ?>" style="width: 100%;">
                                </td>
                                <td>
                                    <input type="number" name="detail_unit_price[]" id="detail_unit_price_<?= $i ?>"
                                           value="<?= $detailUnitPrice ?>" style="width: 100%;"
                                           onchange="calculateLine(<?= $i ?>)">
                                </td>
                                <td>
                                    <input type="number" name="detail_amount[]" id="detail_amount_<?= $i ?>"
                                           value="<?= $detailAmount ?>" style="width: 100%;"
                                           onchange="calculateTotal()">
                                </td>
                                <td>
                                    <input type="number" name="detail_tax_rate[]" id="detail_tax_rate_<?= $i ?>"
                                           value="<?= $detailTaxRate ?>" style="width: 100%; step="0.1">
                                </td>
                                <td>
                                    <input type="text" name="detail_remarks[]" id="detail_remarks_<?= $i ?>"
                                           value="<?= htmlspecialchars($detailRemarks) ?>" style="width: 100%;">
                                </td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>

                <div class="slip-total">
                    <div class="total-item">
                        <div class="total-label">税抜合計</div>
                        <div class="total-value" id="total_amount"><?= number_format($slip['total_amount'] ?? 0) ?></div>
                    </div>
                    <div class="total-item">
                        <div class="total-label">消費税</div>
                        <div class="total-value" id="total_tax"><?= number_format($slip['tax_amount'] ?? 0) ?></div>
                    </div>
                    <div class="total-item">
                        <div class="total-label">税込合計</div>
                        <div class="total-value" id="total_incl"><?= number_format(($slip['total_amount'] ?? 0) + ($slip['tax_amount'] ?? 0)) ?></div>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="staff_print" value="1" <?= ($slip['staff_print'] ?? 0) ? 'checked' : '' ?>>
                            担当者名を印字する
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="invoice_remarks_print" value="1" <?= ($slip['invoice_remarks_print'] ?? 0) ? 'checked' : '' ?>>
                            請求書摘要を印字する
                        </label>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">登録</button>
                    <?php if ($action === 'edit'): ?>
                        <a href="/sales/input.php" class="btn btn-secondary">新規入力へ</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($action === 'edit'): ?>
            <div style="display: flex; gap: 10px; margin-top: 20px; justify-content: center;">
                <form method="post" action="/sales/input.php" style="display: inline;" onsubmit="return confirm('この伝票を削除しますか？');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button type="submit" class="btn btn-danger">削除</button>
                </form>
                <form method="post" action="/sales/input.php" style="display: inline;" onsubmit="return confirm('この伝票を複写しますか？');">
                    <input type="hidden" name="action" value="copy">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button type="submit" class="btn btn-secondary">複写</button>
                </form>
                <form method="post" action="/sales/input.php" style="display: inline;" onsubmit="return confirm('この伝票の赤伝を登録しますか？');">
                    <input type="hidden" name="action" value="red_slip">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button type="submit" class="btn btn-danger">赤伝</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
