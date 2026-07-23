<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';
require_once __DIR__ . '/../../Helpers/Csrf.php';
require_once __DIR__ . '/../../Helpers/Validator.php';
require_once __DIR__ . '/../../Models/BaseModel.php';
require_once __DIR__ . '/../../Models/Customer.php';

Session::start();
Auth::requireLogin();
Auth::requireFiscalYear();

$customerModel = new Customer();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

// POST処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create' || $postAction === 'update') {
        $data = [
            'customer_code' => trim($_POST['customer_code'] ?? ''),
            'customer_name' => trim($_POST['customer_name'] ?? ''),
            'customer_name_kana' => trim($_POST['customer_name_kana'] ?? ''),
            'customer_name_short' => trim($_POST['customer_name_short'] ?? ''),
            'customer_honorific' => trim($_POST['customer_honorific'] ?? '御中'),
            'postal_code' => trim($_POST['postal_code'] ?? ''),
            'address1' => trim($_POST['address1'] ?? ''),
            'address2' => trim($_POST['address2'] ?? ''),
            'address3' => trim($_POST['address3'] ?? ''),
            'tel' => trim($_POST['tel'] ?? ''),
            'fax' => trim($_POST['fax'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'homepage' => trim($_POST['homepage'] ?? ''),
            'customer_staff_name' => trim($_POST['customer_staff_name'] ?? ''),
            'staff_honorific' => trim($_POST['staff_honorific'] ?? '様'),
            'department_name' => trim($_POST['department_name'] ?? ''),
            'position_name' => trim($_POST['position_name'] ?? ''),
            'price_type' => (int)($_POST['price_type'] ?? 1),
            'tax_fraction_method' => (int)($_POST['tax_fraction_method'] ?? 1),
            'tax_processing' => (int)($_POST['tax_processing'] ?? 1),
            'billing_method' => (int)($_POST['billing_method'] ?? 0),
            'closing_day' => (int)($_POST['closing_day'] ?? 31),
            'opening_accounts_receivable' => (int)($_POST['opening_accounts_receivable'] ?? 0),
            'remarks' => trim($_POST['remarks'] ?? ''),
        ];

        // バリデーション
        $validator = new Validator($data);
        $validator->required('customer_code', '得意先コード')
                  ->maxLength('customer_code', 14, '得意先コード')
                  ->required('customer_name', '得意先名')
                  ->maxLength('customer_name', 40, '得意先名')
                  ->required('customer_name_kana', '得意先名カナ')
                  ->maxLength('customer_name_kana', 80, '得意先名カナ')
                  ->required('customer_honorific', '得意先敬称')
                  ->required('postal_code', '郵便番号')
                  ->digits('postal_code', 7, '郵便番号')
                  ->required('address1', '住所1')
                  ->required('address2', '住所2')
                  ->required('price_type', '単価種別')
                  ->inArray('price_type', [1,2,3,4,5], '単価種別')
                  ->required('tax_fraction_method', '税端数処理')
                  ->inArray('tax_fraction_method', [1,2,3], '税端数処理')
                  ->required('tax_processing', '税処理')
                  ->inArray('tax_processing', [1,2,3,4,5,6], '税処理')
                  ->required('billing_method', '請求方法')
                  ->inArray('billing_method', [0,1], '請求方法');

        if ($validator->hasErrors()) {
            $error = $validator->getFirstError();
        } else {
            try {
                // コード重複チェック
                if ($postAction === 'create') {
                    $existing = $customerModel->findByCode($data['customer_code']);
                    if ($existing) {
                        $error = 'この得意先コードは既に登録されています。';
                    }
                }

                if (!$error) {
                    if ($postAction === 'update') {
                        // コード変更不可チェック
                        $existing = $customerModel->findById($id);
                        if ($existing && $existing['customer_code'] !== $data['customer_code']) {
                            $error = '得意先コードは変更できません。';
                        } else {
                            $customerModel->update($id, $data);
                            $success = '得意先を更新しました。';
                            $action = 'edit';
                        }
                    } else {
                        $customerModel->create($data);
                        $success = '得意先を登録しました。';
                        $action = 'list';
                    }
                }
            } catch (Exception $e) {
                $error = '登録に失敗しました。';
            }
        }
    }

    if ($postAction === 'delete') {
        $targetId = (int)($_POST['id'] ?? 0);
        if ($customerModel->isInUse($targetId)) {
            $error = '売上伝票で使用中の得意先は削除できません。';
        } else {
            $customerModel->delete($targetId);
            $success = '得意先を削除しました。';
            $action = 'list';
        }
    }
}

// データ取得
$customer = null;
$customers = null;

if ($action === 'edit' && $id) {
    $customer = $customerModel->findById($id);
    if (!$customer) {
        $action = 'list';
        $error = '得意先が見つかりません。';
    }
} elseif ($action === 'list') {
    $page = (int)($_GET['page'] ?? 1);
    $keyword = $_GET['keyword'] ?? '';
    $customers = $customerModel->search(['keyword' => $keyword], $page);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>得意先マスタ - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <a href="/top.php" style="font-size: 18px; color: #1e293b;"><?= APP_NAME ?></a>
            <span style="margin-left: 16px; color: #64748b;">得意先マスタ</span>
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

        <?php if ($action === 'list'): ?>
        <!-- 一覧画面 -->
        <div class="search-form">
            <form method="get" action="/master/customer.php">
                <div class="form-row">
                    <div class="form-group" style="flex: 3;">
                        <label for="keyword">検索</label>
                        <input type="text" id="keyword" name="keyword"
                               value="<?= htmlspecialchars($_GET['keyword'] ?? '') ?>"
                               placeholder="コード、名前、カナで検索">
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
                <h2>得意先一覧</h2>
                <a href="/master/customer.php?action=create" class="btn btn-primary">新規登録</a>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>コード</th>
                        <th>得意先名</th>
                        <th>カナ</th>
                        <th>請求方法</th>
                        <th>TEL</th>
                        <th class="text-center">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers['data'])): ?>
                    <tr>
                        <td colspan="6" class="text-center">データがありません。</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($customers['data'] as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['customer_code']) ?></td>
                        <td><?= htmlspecialchars($row['customer_name']) ?></td>
                        <td><?= htmlspecialchars($row['customer_name_kana']) ?></td>
                        <td><?= $row['billing_method'] == 0 ? '締め請求' : '都度請求' ?></td>
                        <td><?= htmlspecialchars($row['tel'] ?? '') ?></td>
                        <td class="text-center">
                            <a href="/master/customer.php?action=edit&id=<?= $row['id'] ?>" class="btn btn-small btn-secondary">編集</a>
                            <form method="post" action="/master/customer.php" style="display: inline;" onsubmit="return confirm('本当に削除しますか？');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <button type="submit" class="btn btn-small btn-danger">削除</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($customers['total_pages'] > 1): ?>
            <div class="pagination">
                <?php for ($p = 1; $p <= $customers['total_pages']; $p++): ?>
                    <?php if ($p == $customers['page']): ?>
                        <span class="current"><?= $p ?></span>
                    <?php else: ?>
                        <a href="/master/customer.php?page=<?= $p ?>&keyword=<?= urlencode($_GET['keyword'] ?? '') ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <!-- 登録/編集画面 -->
        <div class="slip-form">
            <h2 style="margin-bottom: 20px;"><?= $action === 'edit' ? '得意先編集' : '得意先登録' ?></h2>

            <form method="post" action="/master/customer.php">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update' : 'create' ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="customer_code">得意先コード <span style="color: red;">*</span></label>
                        <input type="text" id="customer_code" name="customer_code" maxlength="14"
                               value="<?= htmlspecialchars($customer['customer_code'] ?? '') ?>" required
                               <?= $action === 'edit' ? 'readonly style="background: #f1f5f9;"' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label for="customer_name">得意先名 <span style="color: red;">*</span></label>
                        <input type="text" id="customer_name" name="customer_name" maxlength="40"
                               value="<?= htmlspecialchars($customer['customer_name'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="customer_name_kana">得意先名カナ <span style="color: red;">*</span></label>
                        <input type="text" id="customer_name_kana" name="customer_name_kana" maxlength="80"
                               value="<?= htmlspecialchars($customer['customer_name_kana'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="customer_name_short">略称</label>
                        <input type="text" id="customer_name_short" name="customer_name_short" maxlength="14"
                               value="<?= htmlspecialchars($customer['customer_name_short'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="customer_honorific">得意先敬称 <span style="color: red;">*</span></label>
                        <input type="text" id="customer_honorific" name="customer_honorific" maxlength="4"
                               value="<?= htmlspecialchars($customer['customer_honorific'] ?? '御中') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="postal_code">郵便番号 <span style="color: red;">*</span></label>
                        <input type="text" id="postal_code" name="postal_code" maxlength="7"
                               value="<?= htmlspecialchars($customer['postal_code'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="address1">住所1（都道府県） <span style="color: red;">*</span></label>
                        <input type="text" id="address1" name="address1" maxlength="40"
                               value="<?= htmlspecialchars($customer['address1'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="address2">住所2（市区町村） <span style="color: red;">*</span></label>
                        <input type="text" id="address2" name="address2" maxlength="40"
                               value="<?= htmlspecialchars($customer['address2'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="address3">住所3（建物名）</label>
                        <input type="text" id="address3" name="address3" maxlength="40"
                               value="<?= htmlspecialchars($customer['address3'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="tel">TEL</label>
                        <input type="text" id="tel" name="tel" maxlength="20"
                               value="<?= htmlspecialchars($customer['tel'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="fax">FAX</label>
                        <input type="text" id="fax" name="fax" maxlength="20"
                               value="<?= htmlspecialchars($customer['fax'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">メールアドレス</label>
                        <input type="email" id="email" name="email" maxlength="100"
                               value="<?= htmlspecialchars($customer['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="customer_staff_name">得意先担当者名</label>
                        <input type="text" id="customer_staff_name" name="customer_staff_name" maxlength="20"
                               value="<?= htmlspecialchars($customer['customer_staff_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="staff_honorific">担当者敬称</label>
                        <input type="text" id="staff_honorific" name="staff_honorific" maxlength="5"
                               value="<?= htmlspecialchars($customer['staff_honorific'] ?? '様') ?>">
                    </div>
                </div>

                <h2 style="margin: 24px 0 16px; padding-top: 24px; border-top: 1px solid #e2e8f0;">請求・税設定</h2>

                <div class="form-row">
                    <div class="form-group">
                        <label for="billing_method">請求方法 <span style="color: red;">*</span></label>
                        <select id="billing_method" name="billing_method" required>
                            <option value="0" <?= ($customer['billing_method'] ?? 0) == 0 ? 'selected' : '' ?>>締め請求</option>
                            <option value="1" <?= ($customer['billing_method'] ?? 0) == 1 ? 'selected' : '' ?>>都度請求</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="closing_day">締日指定</label>
                        <select id="closing_day" name="closing_day">
                            <?php for ($d = 1; $d <= 27; $d++): ?>
                            <option value="<?= $d ?>" <?= ($customer['closing_day'] ?? 31) == $d ? 'selected' : '' ?>><?= $d ?>日</option>
                            <?php endfor; ?>
                            <option value="31" <?= ($customer['closing_day'] ?? 31) == 31 ? 'selected' : '' ?>>月末</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="tax_processing">税処理 <span style="color: red;">*</span></label>
                        <select id="tax_processing" name="tax_processing" required>
                            <option value="1" <?= ($customer['tax_processing'] ?? 1) == 1 ? 'selected' : '' ?>>外税/伝票計</option>
                            <option value="2" <?= ($customer['tax_processing'] ?? 1) == 2 ? 'selected' : '' ?>>外税/請求時</option>
                            <option value="3" <?= ($customer['tax_processing'] ?? 1) == 3 ? 'selected' : '' ?>>内税/伝票計</option>
                            <option value="4" <?= ($customer['tax_processing'] ?? 1) == 4 ? 'selected' : '' ?>>内税/請求時</option>
                            <option value="5" <?= ($customer['tax_processing'] ?? 1) == 5 ? 'selected' : '' ?>>免税</option>
                            <option value="6" <?= ($customer['tax_processing'] ?? 1) == 6 ? 'selected' : '' ?>>外税/手入力</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tax_fraction_method">税端数処理 <span style="color: red;">*</span></label>
                        <select id="tax_fraction_method" name="tax_fraction_method" required>
                            <option value="1" <?= ($customer['tax_fraction_method'] ?? 1) == 1 ? 'selected' : '' ?>>切捨て</option>
                            <option value="2" <?= ($customer['tax_fraction_method'] ?? 1) == 2 ? 'selected' : '' ?>>切上げ</option>
                            <option value="3" <?= ($customer['tax_fraction_method'] ?? 1) == 3 ? 'selected' : '' ?>>四捨五入</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="price_type">単価種別 <span style="color: red;">*</span></label>
                        <select id="price_type" name="price_type" required>
                            <option value="1" <?= ($customer['price_type'] ?? 1) == 1 ? 'selected' : '' ?>>売上単価1</option>
                            <option value="2" <?= ($customer['price_type'] ?? 1) == 2 ? 'selected' : '' ?>>売上単価2</option>
                            <option value="3" <?= ($customer['price_type'] ?? 1) == 3 ? 'selected' : '' ?>>売上単価3</option>
                            <option value="4" <?= ($customer['price_type'] ?? 1) == 4 ? 'selected' : '' ?>>売上単価4</option>
                            <option value="5" <?= ($customer['price_type'] ?? 1) == 5 ? 'selected' : '' ?>>売上原価</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="opening_accounts_receivable">期首売掛残高</label>
                        <input type="number" id="opening_accounts_receivable" name="opening_accounts_receivable"
                               value="<?= htmlspecialchars($customer['opening_accounts_receivable'] ?? 0) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="remarks">備考</label>
                        <input type="text" id="remarks" name="remarks" maxlength="60"
                               value="<?= htmlspecialchars($customer['remarks'] ?? '') ?>">
                    </div>
                </div>

                <div style="text-align: center; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">登録</button>
                    <a href="/master/customer.php" class="btn btn-secondary">一覧に戻る</a>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
