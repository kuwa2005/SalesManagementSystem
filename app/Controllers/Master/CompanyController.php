<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';
require_once __DIR__ . '/../../Helpers/Csrf.php';
require_once __DIR__ . '/../../Helpers/Validator.php';
require_once __DIR__ . '/../../Models/BaseModel.php';
require_once __DIR__ . '/../../Models/Company.php';

Session::start();
Auth::requireLogin();
Auth::requireFiscalYear();

$company = new Company();
$companyInfo = $company->getCurrent();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();

    $data = [
        'company_name' => trim($_POST['company_name'] ?? ''),
        'company_name_kana' => trim($_POST['company_name_kana'] ?? ''),
        'postal_code' => trim($_POST['postal_code'] ?? ''),
        'address1' => trim($_POST['address1'] ?? ''),
        'address2' => trim($_POST['address2'] ?? ''),
        'address3' => trim($_POST['address3'] ?? ''),
        'tel' => trim($_POST['tel'] ?? ''),
        'fax' => trim($_POST['fax'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'homepage' => trim($_POST['homepage'] ?? ''),
        'invoice_registration_number' => trim($_POST['invoice_registration_number'] ?? ''),
        'slip_numbering_method' => (int)($_POST['slip_numbering_method'] ?? 1),
        'invoice_numbering_method' => (int)($_POST['invoice_numbering_method'] ?? 1),
        'unit_price_fraction_method' => (int)($_POST['unit_price_fraction_method'] ?? 1),
        'quantity_decimal_places' => (int)($_POST['quantity_decimal_places'] ?? 0),
        'amount_fraction_method' => (int)($_POST['amount_fraction_method'] ?? 1),
        'cost_price_decimal_places' => (int)($_POST['cost_price_decimal_places'] ?? 0),
        'selling_price_decimal_places' => (int)($_POST['selling_price_decimal_places'] ?? 0),
        'fiscal_month' => (int)($_POST['fiscal_month'] ?? 3),
        'closing_day' => (int)($_POST['closing_day'] ?? 31),
        'company_name_print' => isset($_POST['company_name_print']) ? 1 : 0,
        'department_address_print' => isset($_POST['department_address_print']) ? 1 : 0,
        'address_print' => isset($_POST['address_print']) ? 1 : 0,
        'stamp_print' => isset($_POST['stamp_print']) ? 1 : 0,
        'selling_price1_name' => trim($_POST['selling_price1_name'] ?? '売上単価1'),
        'selling_price2_name' => trim($_POST['selling_price2_name'] ?? '売上単価2'),
        'selling_price3_name' => trim($_POST['selling_price3_name'] ?? '売上単価3'),
        'selling_price4_name' => trim($_POST['selling_price4_name'] ?? '売上単価4'),
        'bank_info1' => trim($_POST['bank_info1'] ?? ''),
        'bank_info2' => trim($_POST['bank_info2'] ?? ''),
        'bank_info3' => trim($_POST['bank_info3'] ?? ''),
        'invoice_title' => trim($_POST['invoice_title'] ?? '請求書'),
        'payment_term' => trim($_POST['payment_term'] ?? ''),
        'remarks' => trim($_POST['remarks'] ?? ''),
    ];

    // バリデーション
    $validator = new Validator($data);
    $validator->required('company_name', '会社名')
              ->maxLength('company_name', 40, '会社名')
              ->required('postal_code', '郵便番号')
              ->digits('postal_code', 7, '郵便番号')
              ->required('address1', '会社住所1')
              ->maxLength('address1', 40, '会社住所1')
              ->required('address2', '会社住所2')
              ->maxLength('address2', 40, '会社住所2')
              ->required('tel', 'TEL')
              ->maxLength('tel', 20, 'TEL');

    if ($validator->hasErrors()) {
        $error = $validator->getFirstError();
    } else {
        try {
            $company->save($data);
            $success = '基本情報を登録しました。';
            $companyInfo = $company->getCurrent();
        } catch (Exception $e) {
            $error = '登録に失敗しました。';
        }
    }
}

// 再読み込み
$companyInfo = $company->getCurrent();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>基本情報登録 - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <a href="/top.php" style="font-size: 18px; color: #1e293b;"><?= APP_NAME ?></a>
            <span style="margin-left: 16px; color: #64748b;">基本情報登録</span>
        </div>
        <div class="header-right">
            <span class="fiscal-year"><?= htmlspecialchars(Session::get('fiscal_year_label')) ?>年度</span>
            <a href="/top.php" class="btn btn-small btn-secondary">TOP</a>
        </div>
    </header>

    <main style="padding: 24px; max-width: 900px; margin: 0 auto;">
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="slip-form">
            <h2 style="margin-bottom: 20px;">会社情報</h2>

            <form method="post" action="/master/company.php">
                <?= Csrf::field() ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="company_name">会社名 <span style="color: red;">*</span></label>
                        <input type="text" id="company_name" name="company_name" maxlength="40"
                               value="<?= htmlspecialchars($companyInfo['company_name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="company_name_kana">会社名カナ</label>
                        <input type="text" id="company_name_kana" name="company_name_kana" maxlength="40"
                               value="<?= htmlspecialchars($companyInfo['company_name_kana'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="postal_code">郵便番号 <span style="color: red;">*</span></label>
                        <input type="text" id="postal_code" name="postal_code" maxlength="7"
                               value="<?= htmlspecialchars($companyInfo['postal_code'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="tel">TEL <span style="color: red;">*</span></label>
                        <input type="text" id="tel" name="tel" maxlength="20"
                               value="<?= htmlspecialchars($companyInfo['tel'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="fax">FAX</label>
                        <input type="text" id="fax" name="fax" maxlength="20"
                               value="<?= htmlspecialchars($companyInfo['fax'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="address1">会社住所1（都道府県） <span style="color: red;">*</span></label>
                        <input type="text" id="address1" name="address1" maxlength="40"
                               value="<?= htmlspecialchars($companyInfo['address1'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="address2">会社住所2（市区町村） <span style="color: red;">*</span></label>
                        <input type="text" id="address2" name="address2" maxlength="40"
                               value="<?= htmlspecialchars($companyInfo['address2'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="address3">会社住所3（建物名）</label>
                        <input type="text" id="address3" name="address3" maxlength="40"
                               value="<?= htmlspecialchars($companyInfo['address3'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">メールアドレス</label>
                        <input type="email" id="email" name="email" maxlength="100"
                               value="<?= htmlspecialchars($companyInfo['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="homepage">ホームページ</label>
                        <input type="url" id="homepage" name="homepage" maxlength="100"
                               value="<?= htmlspecialchars($companyInfo['homepage'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="invoice_registration_number">適格請求書発行事業者登録番号</label>
                        <input type="text" id="invoice_registration_number" name="invoice_registration_number" maxlength="13"
                               value="<?= htmlspecialchars($companyInfo['invoice_registration_number'] ?? '') ?>">
                    </div>
                </div>

                <h2 style="margin: 24px 0 16px; padding-top: 24px; border-top: 1px solid #e2e8f0;">初期設定</h2>

                <div class="form-row">
                    <div class="form-group">
                        <label for="slip_numbering_method">伝票付番方法 <span style="color: red;">*</span></label>
                        <select id="slip_numbering_method" name="slip_numbering_method" required>
                            <option value="1" <?= ($companyInfo['slip_numbering_method'] ?? 1) == 1 ? 'selected' : '' ?>>自動付番（年度）</option>
                            <option value="2" <?= ($companyInfo['slip_numbering_method'] ?? 1) == 2 ? 'selected' : '' ?>>自動付番（月度）</option>
                            <option value="3" <?= ($companyInfo['slip_numbering_method'] ?? 1) == 3 ? 'selected' : '' ?>>手入力</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="invoice_numbering_method">請求書付番方法 <span style="color: red;">*</span></label>
                        <select id="invoice_numbering_method" name="invoice_numbering_method" required>
                            <option value="1" <?= ($companyInfo['invoice_numbering_method'] ?? 1) == 1 ? 'selected' : '' ?>>自動付番（年度）</option>
                            <option value="2" <?= ($companyInfo['invoice_numbering_method'] ?? 1) == 2 ? 'selected' : '' ?>>自動付番（月度）</option>
                            <option value="3" <?= ($companyInfo['invoice_numbering_method'] ?? 1) == 3 ? 'selected' : '' ?>>手入力</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="unit_price_fraction_method">単価端数処理 <span style="color: red;">*</span></label>
                        <select id="unit_price_fraction_method" name="unit_price_fraction_method" required>
                            <option value="1" <?= ($companyInfo['unit_price_fraction_method'] ?? 1) == 1 ? 'selected' : '' ?>>切捨て</option>
                            <option value="2" <?= ($companyInfo['unit_price_fraction_method'] ?? 1) == 2 ? 'selected' : '' ?>>切上げ</option>
                            <option value="3" <?= ($companyInfo['unit_price_fraction_method'] ?? 1) == 3 ? 'selected' : '' ?>>四捨五入</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="amount_fraction_method">金額端数処理 <span style="color: red;">*</span></label>
                        <select id="amount_fraction_method" name="amount_fraction_method" required>
                            <option value="1" <?= ($companyInfo['amount_fraction_method'] ?? 1) == 1 ? 'selected' : '' ?>>切捨て</option>
                            <option value="2" <?= ($companyInfo['amount_fraction_method'] ?? 1) == 2 ? 'selected' : '' ?>>切上げ</option>
                            <option value="3" <?= ($companyInfo['amount_fraction_method'] ?? 1) == 3 ? 'selected' : '' ?>>四捨五入</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="quantity_decimal_places">数量小数桁数</label>
                        <select id="quantity_decimal_places" name="quantity_decimal_places">
                            <option value="0" <?= ($companyInfo['quantity_decimal_places'] ?? 0) == 0 ? 'selected' : '' ?>>0</option>
                            <option value="1" <?= ($companyInfo['quantity_decimal_places'] ?? 0) == 1 ? 'selected' : '' ?>>1</option>
                            <option value="2" <?= ($companyInfo['quantity_decimal_places'] ?? 0) == 2 ? 'selected' : '' ?>>2</option>
                            <option value="3" <?= ($companyInfo['quantity_decimal_places'] ?? 0) == 3 ? 'selected' : '' ?>>3</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="selling_price_decimal_places">単価小数桁数</label>
                        <select id="selling_price_decimal_places" name="selling_price_decimal_places">
                            <option value="0" <?= ($companyInfo['selling_price_decimal_places'] ?? 0) == 0 ? 'selected' : '' ?>>0</option>
                            <option value="1" <?= ($companyInfo['selling_price_decimal_places'] ?? 0) == 1 ? 'selected' : '' ?>>1</option>
                            <option value="2" <?= ($companyInfo['selling_price_decimal_places'] ?? 0) == 2 ? 'selected' : '' ?>>2</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="cost_price_decimal_places">原単価小数桁数</label>
                        <select id="cost_price_decimal_places" name="cost_price_decimal_places">
                            <option value="0" <?= ($companyInfo['cost_price_decimal_places'] ?? 0) == 0 ? 'selected' : '' ?>>0</option>
                            <option value="1" <?= ($companyInfo['cost_price_decimal_places'] ?? 0) == 1 ? 'selected' : '' ?>>1</option>
                            <option value="2" <?= ($companyInfo['cost_price_decimal_places'] ?? 0) == 2 ? 'selected' : '' ?>>2</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="fiscal_month">本年度決算月</label>
                        <select id="fiscal_month" name="fiscal_month">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= ($companyInfo['fiscal_month'] ?? 3) == $m ? 'selected' : '' ?>><?= $m ?>月</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="closing_day">自社締日</label>
                        <select id="closing_day" name="closing_day">
                            <?php for ($d = 1; $d <= 27; $d++): ?>
                            <option value="<?= $d ?>" <?= ($companyInfo['closing_day'] ?? 31) == $d ? 'selected' : '' ?>><?= $d ?>日</option>
                            <?php endfor; ?>
                            <option value="31" <?= ($companyInfo['closing_day'] ?? 31) == 31 ? 'selected' : '' ?>>月末</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="company_name_print" value="1"
                                   <?= ($companyInfo['company_name_print'] ?? 1) ? 'checked' : '' ?>>
                            会社名を帳票に印刷する
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="address_print" value="1"
                                   <?= ($companyInfo['address_print'] ?? 1) ? 'checked' : '' ?>>
                            会社住所を帳票に印刷する
                        </label>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="stamp_print" value="1"
                                   <?= ($companyInfo['stamp_print'] ?? 0) ? 'checked' : '' ?>>
                            請求書に印鑑を印刷する
                        </label>
                    </div>
                </div>

                <h2 style="margin: 24px 0 16px; padding-top: 24px; border-top: 1px solid #e2e8f0;">帳票設定</h2>

                <div class="form-row">
                    <div class="form-group">
                        <label for="invoice_title">請求書タイトル</label>
                        <input type="text" id="invoice_title" name="invoice_title" maxlength="9"
                               value="<?= htmlspecialchars($companyInfo['invoice_title'] ?? '請求書') ?>">
                    </div>
                    <div class="form-group">
                        <label for="payment_term">支払期限</label>
                        <input type="text" id="payment_term" name="payment_term" maxlength="25"
                               value="<?= htmlspecialchars($companyInfo['payment_term'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="selling_price1_name">売上単価1名称</label>
                        <input type="text" id="selling_price1_name" name="selling_price1_name" maxlength="20"
                               value="<?= htmlspecialchars($companyInfo['selling_price1_name'] ?? '売上単価1') ?>">
                    </div>
                    <div class="form-group">
                        <label for="selling_price2_name">売上単価2名称</label>
                        <input type="text" id="selling_price2_name" name="selling_price2_name" maxlength="20"
                               value="<?= htmlspecialchars($companyInfo['selling_price2_name'] ?? '売上単価2') ?>">
                    </div>
                    <div class="form-group">
                        <label for="selling_price3_name">売上単価3名称</label>
                        <input type="text" id="selling_price3_name" name="selling_price3_name" maxlength="20"
                               value="<?= htmlspecialchars($companyInfo['selling_price3_name'] ?? '売上単価3') ?>">
                    </div>
                    <div class="form-group">
                        <label for="selling_price4_name">売上単価4名称</label>
                        <input type="text" id="selling_price4_name" name="selling_price4_name" maxlength="20"
                               value="<?= htmlspecialchars($companyInfo['selling_price4_name'] ?? '売上単価4') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="bank_info1">口座情報1</label>
                        <input type="text" id="bank_info1" name="bank_info1" maxlength="40"
                               value="<?= htmlspecialchars($companyInfo['bank_info1'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="bank_info2">口座情報2</label>
                        <input type="text" id="bank_info2" name="bank_info2" maxlength="40"
                               value="<?= htmlspecialchars($companyInfo['bank_info2'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="bank_info3">口座情報3</label>
                        <input type="text" id="bank_info3" name="bank_info3" maxlength="40"
                               value="<?= htmlspecialchars($companyInfo['bank_info3'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="remarks">備考</label>
                        <input type="text" id="remarks" name="remarks" maxlength="60"
                               value="<?= htmlspecialchars($companyInfo['remarks'] ?? '') ?>">
                    </div>
                </div>

                <div style="text-align: center; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">登録</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
