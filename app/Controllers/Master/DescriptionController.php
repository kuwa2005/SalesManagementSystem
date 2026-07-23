<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';
require_once __DIR__ . '/../../Helpers/Csrf.php';
require_once __DIR__ . '/../../Helpers/Validator.php';
require_once __DIR__ . '/../../Models/BaseModel.php';
require_once __DIR__ . '/../../Models/Description.php';

Session::start();
Auth::requireLogin();
Auth::requireFiscalYear();

$descModel = new Description();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create' || $postAction === 'update') {
        $data = [
            'description_type' => (int)($_POST['description_type'] ?? 1),
            'description_code' => trim($_POST['description_code'] ?? ''),
            'description_name' => trim($_POST['description_name'] ?? ''),
        ];

        $validator = new Validator($data);
        $validator->required('description_type', '摘要種別')
                  ->inArray('description_type', [1,2,3,4], '摘要種別')
                  ->required('description_code', '摘要コード')
                  ->maxLength('description_code', 4, '摘要コード')
                  ->required('description_name', '摘要名')
                  ->maxLength('description_name', 40, '摘要名');

        if ($validator->hasErrors()) {
            $error = $validator->getFirstError();
        } else {
            try {
                if ($postAction === 'create') {
                    $existing = $descModel->findByCode($data['description_code']);
                    if ($existing) {
                        $error = 'この摘要コードは既に登録されています。';
                    }
                }

                if (!$error) {
                    if ($postAction === 'update') {
                        $existing = $descModel->findById($id);
                        if ($existing && $existing['description_code'] !== $data['description_code']) {
                            $error = '摘要コードは変更できません。';
                        } else {
                            $descModel->update($id, $data);
                            $success = '摘要を更新しました。';
                            $action = 'edit';
                        }
                    } else {
                        $descModel->create($data);
                        $success = '摘要を登録しました。';
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
        $descModel->delete($targetId);
        $success = '摘要を削除しました。';
        $action = 'list';
    }
}

$description = null;
$descriptions = null;

if ($action === 'edit' && $id) {
    $description = $descModel->findById($id);
    if (!$description) {
        $action = 'list';
        $error = '摘要が見つかりません。';
    }
} elseif ($action === 'list') {
    $page = (int)($_GET['page'] ?? 1);
    $keyword = $_GET['keyword'] ?? '';
    $type = (int)($_GET['type'] ?? 0);
    $descriptions = $descModel->search(['keyword' => $keyword, 'type' => $type > 0 ? $type : ''], $page);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>摘要マスタ - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <a href="/top.php" style="font-size: 18px; color: #1e293b;"><?= APP_NAME ?></a>
            <span style="margin-left: 16px; color: #64748b;">摘要マスタ</span>
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
            <form method="get" action="/master/description.php">
                <div class="form-row">
                    <div class="form-group">
                        <label for="type">摘要種別</label>
                        <select id="type" name="type">
                            <option value="">全て</option>
                            <option value="1" <?= ($_GET['type'] ?? '') == 1 ? 'selected' : '' ?>>売上伝票</option>
                            <option value="2" <?= ($_GET['type'] ?? '') == 2 ? 'selected' : '' ?>>入金伝票</option>
                            <option value="3" <?= ($_GET['type'] ?? '') == 3 ? 'selected' : '' ?>>請求書</option>
                            <option value="4" <?= ($_GET['type'] ?? '') == 4 ? 'selected' : '' ?>>領収書</option>
                        </select>
                    </div>
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
                <h2>摘要一覧</h2>
                <a href="/master/description.php?action=create" class="btn btn-primary">新規登録</a>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>種別</th>
                        <th>コード</th>
                        <th>摘要名</th>
                        <th class="text-center">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($descriptions['data'])): ?>
                    <tr><td colspan="4" class="text-center">データがありません。</td></tr>
                    <?php else: ?>
                    <?php foreach ($descriptions['data'] as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($descModel->getTypeName($row['description_type'])) ?></td>
                        <td><?= htmlspecialchars($row['description_code']) ?></td>
                        <td><?= htmlspecialchars($row['description_name']) ?></td>
                        <td class="text-center">
                            <a href="/master/description.php?action=edit&id=<?= $row['id'] ?>" class="btn btn-small btn-secondary">編集</a>
                            <form method="post" action="/master/description.php" style="display: inline;" onsubmit="return confirm('本当に削除しますか？');">
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

            <?php if ($descriptions['total_pages'] > 1): ?>
            <div class="pagination">
                <?php for ($p = 1; $p <= $descriptions['total_pages']; $p++): ?>
                    <?php if ($p == $descriptions['page']): ?>
                        <span class="current"><?= $p ?></span>
                    <?php else: ?>
                        <a href="/master/description.php?page=<?= $p ?>&keyword=<?= urlencode($_GET['keyword'] ?? '') ?>&type=<?= $_GET['type'] ?? '' ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <div class="slip-form">
            <h2 style="margin-bottom: 20px;"><?= $action === 'edit' ? '摘要編集' : '摘要登録' ?></h2>
            <form method="post" action="/master/description.php">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update' : 'create' ?>">
                <?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="description_type">摘要種別 <span style="color: red;">*</span></label>
                        <select id="description_type" name="description_type" required <?= $action === 'edit' ? 'disabled' : '' ?>>
                            <option value="1" <?= ($description['description_type'] ?? 1) == 1 ? 'selected' : '' ?>>売上伝票</option>
                            <option value="2" <?= ($description['description_type'] ?? 1) == 2 ? 'selected' : '' ?>>入金伝票</option>
                            <option value="3" <?= ($description['description_type'] ?? 1) == 3 ? 'selected' : '' ?>>請求書</option>
                            <option value="4" <?= ($description['description_type'] ?? 1) == 4 ? 'selected' : '' ?>>領収書</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="description_code">摘要コード <span style="color: red;">*</span></label>
                        <input type="text" id="description_code" name="description_code" maxlength="4" value="<?= htmlspecialchars($description['description_code'] ?? '') ?>" required <?= $action === 'edit' ? 'readonly style="background: #f1f5f9;"' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label for="description_name">摘要名 <span style="color: red;">*</span></label>
                        <input type="text" id="description_name" name="description_name" maxlength="40" value="<?= htmlspecialchars($description['description_name'] ?? '') ?>" required>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">登録</button>
                    <a href="/master/description.php" class="btn btn-secondary">一覧に戻る</a>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
