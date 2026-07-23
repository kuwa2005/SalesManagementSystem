<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';
require_once __DIR__ . '/../../Helpers/Csrf.php';
require_once __DIR__ . '/../../Helpers/Validator.php';
require_once __DIR__ . '/../../Models/BaseModel.php';
require_once __DIR__ . '/../../Models/Category.php';

Session::start();
Auth::requireLogin();
Auth::requireFiscalYear();

$catModel = new Category();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create' || $postAction === 'update') {
        $data = [
            'large_code' => trim($_POST['large_code'] ?? ''),
            'large_name' => trim($_POST['large_name'] ?? ''),
            'medium_code' => trim($_POST['medium_code'] ?? ''),
            'medium_name' => trim($_POST['medium_name'] ?? ''),
            'small_code' => trim($_POST['small_code'] ?? ''),
            'small_name' => trim($_POST['small_name'] ?? ''),
        ];

        $validator = new Validator($data);
        $validator->required('large_code', '商品大分類コード')
                  ->maxLength('large_code', 8, '商品大分類コード')
                  ->required('large_name', '商品大分類名')
                  ->maxLength('large_name', 14, '商品大分類名')
                  ->required('medium_code', '商品中分類コード')
                  ->maxLength('medium_code', 14, '商品中分類コード')
                  ->required('medium_name', '商品中分類名')
                  ->maxLength('medium_name', 40, '商品中分類名');

        if ($validator->hasErrors()) {
            $error = $validator->getFirstError();
        } else {
            try {
                if ($postAction === 'create') {
                    $catModel->create($data);
                    $success = 'カテゴリーを登録しました。';
                } else {
                    $catModel->update($id, $data);
                    $success = 'カテゴリーを更新しました。';
                }
                $action = 'list';
            } catch (Exception $e) {
                $error = '登録に失敗しました。';
            }
        }
    }

    if ($postAction === 'delete') {
        $targetId = (int)($_POST['id'] ?? 0);
        if ($catModel->isInUse($targetId)) {
            $error = '商品で使用中のカテゴリーは削除できません。';
        } else {
            $catModel->delete($targetId);
            $success = 'カテゴリーを削除しました。';
            $action = 'list';
        }
    }
}

$category = null;
$categories = null;

if ($action === 'edit' && $id) {
    $category = $catModel->findById($id);
    if (!$category) {
        $action = 'list';
        $error = 'カテゴリーが見つかりません。';
    }
} elseif ($action === 'list') {
    $page = (int)($_GET['page'] ?? 1);
    $keyword = $_GET['keyword'] ?? '';
    $categories = $catModel->search(['keyword' => $keyword], $page);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品カテゴリーマスタ - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/SalesManagementSystem/public/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <a href="/SalesManagementSystem/" style="font-size: 18px; color: #1e293b;"><?= APP_NAME ?></a>
            <span style="margin-left: 16px; color: #64748b;">商品カテゴリーマスタ</span>
        </div>
        <div class="header-right">
            <span class="fiscal-year"><?= htmlspecialchars(Session::get('fiscal_year_label')) ?>年度</span>
            <a href="/SalesManagementSystem/" class="btn btn-small btn-secondary">TOP</a>
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
            <form method="get" action="/SalesManagementSystem/master/category.php">
                <div class="form-row">
                    <div class="form-group" style="flex: 3;">
                        <label for="keyword">検索</label>
                        <input type="text" id="keyword" name="keyword" value="<?= htmlspecialchars($_GET['keyword'] ?? '') ?>" placeholder="分類名で検索">
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
                <h2>商品カテゴリー一覧</h2>
                <a href="/SalesManagementSystem/master/category.php?action=create" class="btn btn-primary">新規登録</a>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>大分類コード</th>
                        <th>大分類名</th>
                        <th>中分類コード</th>
                        <th>中分類名</th>
                        <th>小分類コード</th>
                        <th>小分類名</th>
                        <th class="text-center">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories['data'])): ?>
                    <tr><td colspan="7" class="text-center">データがありません。</td></tr>
                    <?php else: ?>
                    <?php foreach ($categories['data'] as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['large_code']) ?></td>
                        <td><?= htmlspecialchars($row['large_name']) ?></td>
                        <td><?= htmlspecialchars($row['medium_code']) ?></td>
                        <td><?= htmlspecialchars($row['medium_name']) ?></td>
                        <td><?= htmlspecialchars($row['small_code'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['small_name'] ?? '') ?></td>
                        <td class="text-center">
                            <a href="/SalesManagementSystem/master/category.php?action=edit&id=<?= $row['id'] ?>" class="btn btn-small btn-secondary">編集</a>
                            <form method="post" action="/SalesManagementSystem/master/category.php" style="display: inline;" onsubmit="return confirm('本当に削除しますか？');">
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

            <?php if ($categories['total_pages'] > 1): ?>
            <div class="pagination">
                <?php for ($p = 1; $p <= $categories['total_pages']; $p++): ?>
                    <?php if ($p == $categories['page']): ?>
                        <span class="current"><?= $p ?></span>
                    <?php else: ?>
                        <a href="/SalesManagementSystem/master/category.php?page=<?= $p ?>&keyword=<?= urlencode($_GET['keyword'] ?? '') ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <div class="slip-form">
            <h2 style="margin-bottom: 20px;"><?= $action === 'edit' ? 'カテゴリー編集' : 'カテゴリー登録' ?></h2>
            <form method="post" action="/SalesManagementSystem/master/category.php">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update' : 'create' ?>">
                <?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

                <h3 style="margin-bottom: 12px; color: #64748b;">大分類</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="large_code">商品大分類コード <span style="color: red;">*</span></label>
                        <input type="text" id="large_code" name="large_code" maxlength="8" value="<?= htmlspecialchars($category['large_code'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="large_name">商品大分類名 <span style="color: red;">*</span></label>
                        <input type="text" id="large_name" name="large_name" maxlength="14" value="<?= htmlspecialchars($category['large_name'] ?? '') ?>" required>
                    </div>
                </div>

                <h3 style="margin: 16px 0 12px; color: #64748b;">中分類</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="medium_code">商品中分類コード <span style="color: red;">*</span></label>
                        <input type="text" id="medium_code" name="medium_code" maxlength="14" value="<?= htmlspecialchars($category['medium_code'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="medium_name">商品中分類名 <span style="color: red;">*</span></label>
                        <input type="text" id="medium_name" name="medium_name" maxlength="40" value="<?= htmlspecialchars($category['medium_name'] ?? '') ?>" required>
                    </div>
                </div>

                <h3 style="margin: 16px 0 12px; color: #64748b;">小分類（任意）</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="small_code">商品小分類コード</label>
                        <input type="text" id="small_code" name="small_code" maxlength="14" value="<?= htmlspecialchars($category['small_code'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="small_name">商品小分類名</label>
                        <input type="text" id="small_name" name="small_name" maxlength="40" value="<?= htmlspecialchars($category['small_name'] ?? '') ?>">
                    </div>
                </div>

                <div style="text-align: center; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">登録</button>
                    <a href="/SalesManagementSystem/master/category.php" class="btn btn-secondary">一覧に戻る</a>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
