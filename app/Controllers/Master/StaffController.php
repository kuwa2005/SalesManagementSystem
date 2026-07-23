<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';
require_once __DIR__ . '/../../Helpers/Csrf.php';
require_once __DIR__ . '/../../Helpers/Validator.php';
require_once __DIR__ . '/../../Models/BaseModel.php';
require_once __DIR__ . '/../../Models/Staff.php';

Session::start();
Auth::requireLogin();
Auth::requireFiscalYear();

$staffModel = new Staff();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create' || $postAction === 'update') {
        $data = [
            'staff_code' => trim($_POST['staff_code'] ?? ''),
            'staff_name' => trim($_POST['staff_name'] ?? ''),
            'staff_name_kana' => trim($_POST['staff_name_kana'] ?? ''),
        ];

        $validator = new Validator($data);
        $validator->required('staff_code', '担当者コード')
                  ->digits('staff_code', 8, '担当者コード')
                  ->required('staff_name', '担当者名')
                  ->maxLength('staff_name', 20, '担当者名');

        if ($validator->hasErrors()) {
            $error = $validator->getFirstError();
        } else {
            try {
                if ($postAction === 'create') {
                    $existing = $staffModel->findByCode($data['staff_code']);
                    if ($existing) {
                        $error = 'この担当者コードは既に登録されています。';
                    }
                }

                if (!$error) {
                    if ($postAction === 'update') {
                        $existing = $staffModel->findById($id);
                        if ($existing && $existing['staff_code'] !== $data['staff_code']) {
                            $error = '担当者コードは変更できません。';
                        } else {
                            $staffModel->update($id, $data);
                            $success = '担当者を更新しました。';
                            $action = 'edit';
                        }
                    } else {
                        $staffModel->create($data);
                        $success = '担当者を登録しました。';
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
        if ($staffModel->isInUse($targetId)) {
            $error = '売上伝票で使用中の担当者は削除できません。';
        } else {
            $staffModel->delete($targetId);
            $success = '担当者を削除しました。';
            $action = 'list';
        }
    }
}

$staff = null;
$staffList = null;

if ($action === 'edit' && $id) {
    $staff = $staffModel->findById($id);
    if (!$staff) {
        $action = 'list';
        $error = '担当者が見つかりません。';
    }
} elseif ($action === 'list') {
    $page = (int)($_GET['page'] ?? 1);
    $keyword = $_GET['keyword'] ?? '';
    $staffList = $staffModel->search(['keyword' => $keyword], $page);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>自社担当者マスタ - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <a href="/top.php" style="font-size: 18px; color: #1e293b;"><?= APP_NAME ?></a>
            <span style="margin-left: 16px; color: #64748b;">自社担当者マスタ</span>
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
            <form method="get" action="/master/staff.php">
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
                <h2>担当者一覧</h2>
                <a href="/master/staff.php?action=create" class="btn btn-primary">新規登録</a>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>コード</th>
                        <th>担当者名</th>
                        <th>カナ名</th>
                        <th class="text-center">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staffList['data'])): ?>
                    <tr><td colspan="4" class="text-center">データがありません。</td></tr>
                    <?php else: ?>
                    <?php foreach ($staffList['data'] as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['staff_code']) ?></td>
                        <td><?= htmlspecialchars($row['staff_name']) ?></td>
                        <td><?= htmlspecialchars($row['staff_name_kana'] ?? '') ?></td>
                        <td class="text-center">
                            <a href="/master/staff.php?action=edit&id=<?= $row['id'] ?>" class="btn btn-small btn-secondary">編集</a>
                            <form method="post" action="/master/staff.php" style="display: inline;" onsubmit="return confirm('本当に削除しますか？');">
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

            <?php if ($staffList['total_pages'] > 1): ?>
            <div class="pagination">
                <?php for ($p = 1; $p <= $staffList['total_pages']; $p++): ?>
                    <?php if ($p == $staffList['page']): ?>
                        <span class="current"><?= $p ?></span>
                    <?php else: ?>
                        <a href="/master/staff.php?page=<?= $p ?>&keyword=<?= urlencode($_GET['keyword'] ?? '') ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <div class="slip-form">
            <h2 style="margin-bottom: 20px;"><?= $action === 'edit' ? '担当者編集' : '担当者登録' ?></h2>
            <form method="post" action="/master/staff.php">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update' : 'create' ?>">
                <?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="staff_code">担当者コード <span style="color: red;">*</span></label>
                        <input type="text" id="staff_code" name="staff_code" maxlength="8" value="<?= htmlspecialchars($staff['staff_code'] ?? '') ?>" required <?= $action === 'edit' ? 'readonly style="background: #f1f5f9;"' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label for="staff_name">担当者名 <span style="color: red;">*</span></label>
                        <input type="text" id="staff_name" name="staff_name" maxlength="20" value="<?= htmlspecialchars($staff['staff_name'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="staff_name_kana">担当者カナ名</label>
                        <input type="text" id="staff_name_kana" name="staff_name_kana" maxlength="60" value="<?= htmlspecialchars($staff['staff_name_kana'] ?? '') ?>">
                    </div>
                </div>

                <div style="text-align: center; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">登録</button>
                    <a href="/master/staff.php" class="btn btn-secondary">一覧に戻る</a>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
