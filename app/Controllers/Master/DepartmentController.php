<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';
require_once __DIR__ . '/../../Helpers/Csrf.php';
require_once __DIR__ . '/../../Helpers/Validator.php';
require_once __DIR__ . '/../../Models/BaseModel.php';
require_once __DIR__ . '/../../Models/Department.php';

Session::start();
Auth::requireLogin();
Auth::requireFiscalYear();

$deptModel = new Department();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create' || $postAction === 'update') {
        $data = [
            'department_code' => trim($_POST['department_code'] ?? ''),
            'department_name' => trim($_POST['department_name'] ?? ''),
            'department_name_kana' => trim($_POST['department_name_kana'] ?? ''),
            'department_name_short' => trim($_POST['department_name_short'] ?? ''),
            'postal_code' => trim($_POST['postal_code'] ?? ''),
            'address1' => trim($_POST['address1'] ?? ''),
            'address2' => trim($_POST['address2'] ?? ''),
            'address3' => trim($_POST['address3'] ?? ''),
            'tel' => trim($_POST['tel'] ?? ''),
            'fax' => trim($_POST['fax'] ?? ''),
            'name_print' => isset($_POST['name_print']) ? 1 : 0,
            'remarks' => trim($_POST['remarks'] ?? ''),
        ];

        $validator = new Validator($data);
        $validator->required('department_code', '部門コード')
                  ->maxLength('department_code', 14, '部門コード')
                  ->required('department_name', '部門名')
                  ->maxLength('department_name', 40, '部門名')
                  ->required('department_name_kana', '部門名カナ')
                  ->maxLength('department_name_kana', 80, '部門名カナ')
                  ->required('department_name_short', '部門名略称')
                  ->maxLength('department_name_short', 14, '部門名略称')
                  ->required('postal_code', '郵便番号')
                  ->digits('postal_code', 7, '郵便番号')
                  ->required('address1', '住所1')
                  ->required('address2', '住所2')
                  ->required('tel', 'TEL');

        if ($validator->hasErrors()) {
            $error = $validator->getFirstError();
        } else {
            try {
                if ($postAction === 'create') {
                    $existing = $deptModel->findByCode($data['department_code']);
                    if ($existing) {
                        $error = 'この部門コードは既に登録されています。';
                    }
                }

                if (!$error) {
                    if ($postAction === 'update') {
                        $existing = $deptModel->findById($id);
                        if ($existing && $existing['department_code'] !== $data['department_code']) {
                            $error = '部門コードは変更できません。';
                        } else {
                            $deptModel->update($id, $data);
                            $success = '部門を更新しました。';
                            $action = 'edit';
                        }
                    } else {
                        $deptModel->create($data);
                        $success = '部門を登録しました。';
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
        if ($deptModel->isInUse($targetId)) {
            $error = '売上伝票で使用中の部門は削除できません。';
        } else {
            $deptModel->delete($targetId);
            $success = '部門を削除しました。';
            $action = 'list';
        }
    }
}

$department = null;
$departments = null;

if ($action === 'edit' && $id) {
    $department = $deptModel->findById($id);
    if (!$department) {
        $action = 'list';
        $error = '部門が見つかりません。';
    }
} elseif ($action === 'list') {
    $page = (int)($_GET['page'] ?? 1);
    $keyword = $_GET['keyword'] ?? '';
    $departments = $deptModel->search(['keyword' => $keyword], $page);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>自社部門マスタ - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <a href="/top.php" style="font-size: 18px; color: #1e293b;"><?= APP_NAME ?></a>
            <span style="margin-left: 16px; color: #64748b;">自社部門マスタ</span>
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
            <form method="get" action="/master/department.php">
                <div class="form-row">
                    <div class="form-group" style="flex: 3;">
                        <label for="keyword">検索</label>
                        <input type="text" id="keyword" name="keyword" value="<?= htmlspecialchars($_GET['keyword'] ?? '') ?>" placeholder="コード、名前、カナで検索">
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
                <h2>部門一覧</h2>
                <a href="/master/department.php?action=create" class="btn btn-primary">新規登録</a>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>コード</th>
                        <th>部門名</th>
                        <th>略称</th>
                        <th>TEL</th>
                        <th>印刷</th>
                        <th class="text-center">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($departments['data'])): ?>
                    <tr><td colspan="6" class="text-center">データがありません。</td></tr>
                    <?php else: ?>
                    <?php foreach ($departments['data'] as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['department_code']) ?></td>
                        <td><?= htmlspecialchars($row['department_name']) ?></td>
                        <td><?= htmlspecialchars($row['department_name_short']) ?></td>
                        <td><?= htmlspecialchars($row['tel']) ?></td>
                        <td><?= $row['name_print'] ? '○' : '' ?></td>
                        <td class="text-center">
                            <a href="/master/department.php?action=edit&id=<?= $row['id'] ?>" class="btn btn-small btn-secondary">編集</a>
                            <form method="post" action="/master/department.php" style="display: inline;" onsubmit="return confirm('本当に削除しますか？');">
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

            <?php if ($departments['total_pages'] > 1): ?>
            <div class="pagination">
                <?php for ($p = 1; $p <= $departments['total_pages']; $p++): ?>
                    <?php if ($p == $departments['page']): ?>
                        <span class="current"><?= $p ?></span>
                    <?php else: ?>
                        <a href="/master/department.php?page=<?= $p ?>&keyword=<?= urlencode($_GET['keyword'] ?? '') ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <div class="slip-form">
            <h2 style="margin-bottom: 20px;"><?= $action === 'edit' ? '部門編集' : '部門登録' ?></h2>
            <form method="post" action="/master/department.php">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update' : 'create' ?>">
                <?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="department_code">部門コード <span style="color: red;">*</span></label>
                        <input type="text" id="department_code" name="department_code" maxlength="14" value="<?= htmlspecialchars($department['department_code'] ?? '') ?>" required <?= $action === 'edit' ? 'readonly style="background: #f1f5f9;"' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label for="department_name">部門名 <span style="color: red;">*</span></label>
                        <input type="text" id="department_name" name="department_name" maxlength="40" value="<?= htmlspecialchars($department['department_name'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="department_name_kana">部門名カナ <span style="color: red;">*</span></label>
                        <input type="text" id="department_name_kana" name="department_name_kana" maxlength="80" value="<?= htmlspecialchars($department['department_name_kana'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="department_name_short">部門名略称 <span style="color: red;">*</span></label>
                        <input type="text" id="department_name_short" name="department_name_short" maxlength="14" value="<?= htmlspecialchars($department['department_name_short'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="postal_code">郵便番号 <span style="color: red;">*</span></label>
                        <input type="text" id="postal_code" name="postal_code" maxlength="7" value="<?= htmlspecialchars($department['postal_code'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="tel">TEL <span style="color: red;">*</span></label>
                        <input type="text" id="tel" name="tel" maxlength="20" value="<?= htmlspecialchars($department['tel'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="fax">FAX</label>
                        <input type="text" id="fax" name="fax" maxlength="20" value="<?= htmlspecialchars($department['fax'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="address1">住所1（都道府県） <span style="color: red;">*</span></label>
                        <input type="text" id="address1" name="address1" maxlength="40" value="<?= htmlspecialchars($department['address1'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="address2">住所2（市区町村） <span style="color: red;">*</span></label>
                        <input type="text" id="address2" name="address2" maxlength="40" value="<?= htmlspecialchars($department['address2'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="address3">住所3（建物名）</label>
                        <input type="text" id="address3" name="address3" maxlength="40" value="<?= htmlspecialchars($department['address3'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="name_print" value="1" <?= ($department['name_print'] ?? 0) ? 'checked' : '' ?>>
                            部門名を帳票に印刷する
                        </label>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="remarks">備考</label>
                        <input type="text" id="remarks" name="remarks" maxlength="60" value="<?= htmlspecialchars($department['remarks'] ?? '') ?>">
                    </div>
                </div>

                <div style="text-align: center; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">登録</button>
                    <a href="/master/department.php" class="btn btn-secondary">一覧に戻る</a>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
