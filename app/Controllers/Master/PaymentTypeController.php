<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';
require_once __DIR__ . '/../../Helpers/Csrf.php';
require_once __DIR__ . '/../../Helpers/Validator.php';
require_once __DIR__ . '/../../Models/BaseModel.php';
require_once __DIR__ . '/../../Models/PaymentType.php';

Session::start();
Auth::requireLogin();
Auth::requireFiscalYear();

$paymentTypeModel = new PaymentType();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create' || $postAction === 'update') {
        $data = [
            'payment_category' => (int)($_POST['payment_category'] ?? 1),
            'payment_type_code' => trim($_POST['payment_type_code'] ?? ''),
            'payment_type_name' => trim($_POST['payment_type_name'] ?? ''),
        ];

        $validator = new Validator($data);
        $validator->required('payment_category', '入金種別')
                  ->inArray('payment_category', [1,2,3,4], '入金種別')
                  ->required('payment_type_code', '入金区分コード')
                  ->maxLength('payment_type_code', 4, '入金区分コード')
                  ->required('payment_type_name', '入金区分名')
                  ->maxLength('payment_type_name', 20, '入金区分名');

        if ($validator->hasErrors()) {
            $error = $validator->getFirstError();
        } else {
            try {
                if ($postAction === 'create') {
                    $existing = $paymentTypeModel->findByCode($data['payment_type_code']);
                    if ($existing) {
                        $error = 'この入金区分コードは既に登録されています。';
                    }
                }

                if (!$error) {
                    if ($postAction === 'update') {
                        $existing = $paymentTypeModel->findById($id);
                        if ($existing && $existing['payment_type_code'] !== $data['payment_type_code']) {
                            $error = '入金区分コードは変更できません。';
                        } else {
                            $paymentTypeModel->update($id, $data);
                            $success = '入金区分を更新しました。';
                            $action = 'edit';
                        }
                    } else {
                        $paymentTypeModel->create($data);
                        $success = '入金区分を登録しました。';
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
        if ($paymentTypeModel->isInUse($targetId)) {
            $error = '入金伝票で使用中の入金区分は削除できません。';
        } else {
            $paymentTypeModel->delete($targetId);
            $success = '入金区分を削除しました。';
            $action = 'list';
        }
    }
}

$paymentType = null;
$paymentTypes = null;

if ($action === 'edit' && $id) {
    $paymentType = $paymentTypeModel->findById($id);
    if (!$paymentType) {
        $action = 'list';
        $error = '入金区分が見つかりません。';
    }
} elseif ($action === 'list') {
    $page = (int)($_GET['page'] ?? 1);
    $keyword = $_GET['keyword'] ?? '';
    $paymentTypes = $paymentTypeModel->search(['keyword' => $keyword], $page);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>入金区分マスタ - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <a href="/top.php" style="font-size: 18px; color: #1e293b;"><?= APP_NAME ?></a>
            <span style="margin-left: 16px; color: #64748b;">入金区分マスタ</span>
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
        <div class="search-form">
            <form method="get" action="/master/payment-type.php">
                <div class="form-row">
                    <div class="form-group" style="flex: 3;">
                        <label for="keyword">検索</label>
                        <input type="text" id="keyword" name="keyword" value="<?= htmlspecialchars($_GET['keyword'] ?? '') ?>" placeholder="コード、名前で検索">
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
                <h2>入金区分一覧</h2>
                <a href="/master/payment-type.php?action=create" class="btn btn-primary">新規登録</a>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>入金種別</th>
                        <th>コード</th>
                        <th>入金区分名</th>
                        <th class="text-center">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($paymentTypes['data'])): ?>
                    <tr><td colspan="4" class="text-center">データがありません。</td></tr>
                    <?php else: ?>
                    <?php foreach ($paymentTypes['data'] as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($paymentTypeModel->getCategoryName($row['payment_category'])) ?></td>
                        <td><?= htmlspecialchars($row['payment_type_code']) ?></td>
                        <td><?= htmlspecialchars($row['payment_type_name']) ?></td>
                        <td class="text-center">
                            <a href="/master/payment-type.php?action=edit&id=<?= $row['id'] ?>" class="btn btn-small btn-secondary">編集</a>
                            <form method="post" action="/master/payment-type.php" style="display: inline;" onsubmit="return confirm('本当に削除しますか？');">
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

            <?php if ($paymentTypes['total_pages'] > 1): ?>
            <div class="pagination">
                <?php for ($p = 1; $p <= $paymentTypes['total_pages']; $p++): ?>
                    <?php if ($p == $paymentTypes['page']): ?>
                        <span class="current"><?= $p ?></span>
                    <?php else: ?>
                        <a href="/master/payment-type.php?page=<?= $p ?>&keyword=<?= urlencode($_GET['keyword'] ?? '') ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <div class="slip-form">
            <h2 style="margin-bottom: 20px;"><?= $action === 'edit' ? '入金区分編集' : '入金区分登録' ?></h2>
            <form method="post" action="/master/payment-type.php">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update' : 'create' ?>">
                <?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="payment_category">入金種別 <span style="color: red;">*</span></label>
                        <select id="payment_category" name="payment_category" required <?= $action === 'edit' ? 'disabled' : '' ?>>
                            <option value="1" <?= ($paymentType['payment_category'] ?? 1) == 1 ? 'selected' : '' ?>>現金</option>
                            <option value="2" <?= ($paymentType['payment_category'] ?? 1) == 2 ? 'selected' : '' ?>>振込</option>
                            <option value="3" <?= ($paymentType['payment_category'] ?? 1) == 3 ? 'selected' : '' ?>>手数料</option>
                            <option value="4" <?= ($paymentType['payment_category'] ?? 1) == 4 ? 'selected' : '' ?>>手形</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="payment_type_code">入金区分コード <span style="color: red;">*</span></label>
                        <input type="text" id="payment_type_code" name="payment_type_code" maxlength="4" value="<?= htmlspecialchars($paymentType['payment_type_code'] ?? '') ?>" required <?= $action === 'edit' ? 'readonly style="background: #f1f5f9;"' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label for="payment_type_name">入金区分名 <span style="color: red;">*</span></label>
                        <input type="text" id="payment_type_name" name="payment_type_name" maxlength="20" value="<?= htmlspecialchars($paymentType['payment_type_name'] ?? '') ?>" required>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">登録</button>
                    <a href="/master/payment-type.php" class="btn btn-secondary">一覧に戻る</a>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
