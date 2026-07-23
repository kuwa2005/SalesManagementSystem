<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';
require_once __DIR__ . '/../../Helpers/Csrf.php';
require_once __DIR__ . '/../../Helpers/Validator.php';
require_once __DIR__ . '/../../Models/BaseModel.php';
require_once __DIR__ . '/../../Models/Product.php';

Session::start();
Auth::requireLogin();
Auth::requireFiscalYear();

$productModel = new Product();
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
            'product_code' => trim($_POST['product_code'] ?? ''),
            'product_name' => trim($_POST['product_name'] ?? ''),
            'product_name_kana' => trim($_POST['product_name_kana'] ?? ''),
            'large_category_code' => trim($_POST['large_category_code'] ?? ''),
            'medium_category_code' => trim($_POST['medium_category_code'] ?? ''),
            'small_category_code' => trim($_POST['small_category_code'] ?? ''),
            'unit' => trim($_POST['unit'] ?? ''),
            'case_quantity' => (int)($_POST['case_quantity'] ?? 0),
            'tax_category' => (int)($_POST['tax_category'] ?? 1),
            'reduced_tax_flag' => isset($_POST['reduced_tax_flag']) ? 1 : 0,
            'selling_price1_excl' => (int)($_POST['selling_price1_excl'] ?? 0),
            'selling_price1_incl' => (int)($_POST['selling_price1_incl'] ?? 0),
            'selling_price2_excl' => (int)($_POST['selling_price2_excl'] ?? 0),
            'selling_price2_incl' => (int)($_POST['selling_price2_incl'] ?? 0),
            'selling_price3_excl' => (int)($_POST['selling_price3_excl'] ?? 0),
            'selling_price3_incl' => (int)($_POST['selling_price3_incl'] ?? 0),
            'selling_price4_excl' => (int)($_POST['selling_price4_excl'] ?? 0),
            'selling_price4_incl' => (int)($_POST['selling_price4_incl'] ?? 0),
            'cost_price_excl' => (int)($_POST['cost_price_excl'] ?? 0),
            'cost_price_incl' => (int)($_POST['cost_price_incl'] ?? 0),
            'remarks' => trim($_POST['remarks'] ?? ''),
        ];

        $validator = new Validator($data);
        $validator->required('product_code', '商品コード')
                  ->maxLength('product_code', 15, '商品コード')
                  ->required('product_name', '商品名')
                  ->maxLength('product_name', 40, '商品名')
                  ->required('product_name_kana', '商品名カナ')
                  ->maxLength('product_name_kana', 80, '商品名カナ')
                  ->required('tax_category', '課税区分')
                  ->inArray('tax_category', [1,2,3], '課税区分')
                  ->required('selling_price1_excl', '売上単価1')
                  ->numeric('selling_price1_excl', '売上単価1');

        if ($validator->hasErrors()) {
            $error = $validator->getFirstError();
        } else {
            try {
                if ($postAction === 'create') {
                    $existing = $productModel->findByCode($data['product_code']);
                    if ($existing) {
                        $error = 'この商品コードは既に登録されています。';
                    }
                }

                if (!$error) {
                    if ($postAction === 'update') {
                        $existing = $productModel->findById($id);
                        if ($existing && $existing['product_code'] !== $data['product_code']) {
                            $error = '商品コードは変更できません。';
                        } else {
                            $productModel->update($id, $data);
                            $success = '商品を更新しました。';
                            $action = 'edit';
                        }
                    } else {
                        $productModel->create($data);
                        $success = '商品を登録しました。';
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
        if ($productModel->isInUse($targetId)) {
            $error = '売上伝票で使用中の商品は削除できません。';
        } else {
            $productModel->delete($targetId);
            $success = '商品を削除しました。';
            $action = 'list';
        }
    }
}

$product = null;
$products = null;

if ($action === 'edit' && $id) {
    $product = $productModel->findById($id);
    if (!$product) {
        $action = 'list';
        $error = '商品が見つかりません。';
    }
} elseif ($action === 'list') {
    $page = (int)($_GET['page'] ?? 1);
    $keyword = $_GET['keyword'] ?? '';
    $products = $productModel->search(['keyword' => $keyword], $page);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品マスタ - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <a href="/top.php" style="font-size: 18px; color: #1e293b;"><?= APP_NAME ?></a>
            <span style="margin-left: 16px; color: #64748b;">商品マスタ</span>
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
            <form method="get" action="/master/product.php">
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
                <h2>商品一覧</h2>
                <a href="/master/product.php?action=create" class="btn btn-primary">新規登録</a>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>コード</th>
                        <th>商品名</th>
                        <th>カナ</th>
                        <th>単位</th>
                        <th>単価1（税抜）</th>
                        <th>原価</th>
                        <th class="text-center">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products['data'])): ?>
                    <tr>
                        <td colspan="7" class="text-center">データがありません。</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($products['data'] as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['product_code']) ?></td>
                        <td><?= htmlspecialchars($row['product_name']) ?></td>
                        <td><?= htmlspecialchars($row['product_name_kana']) ?></td>
                        <td><?= htmlspecialchars($row['unit'] ?? '') ?></td>
                        <td class="text-right"><?= number_format($row['selling_price1_excl'] ?? 0) ?></td>
                        <td class="text-right"><?= number_format($row['cost_price_excl'] ?? 0) ?></td>
                        <td class="text-center">
                            <a href="/master/product.php?action=edit&id=<?= $row['id'] ?>" class="btn btn-small btn-secondary">編集</a>
                            <form method="post" action="/master/product.php" style="display: inline;" onsubmit="return confirm('本当に削除しますか？');">
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

            <?php if ($products['total_pages'] > 1): ?>
            <div class="pagination">
                <?php for ($p = 1; $p <= $products['total_pages']; $p++): ?>
                    <?php if ($p == $products['page']): ?>
                        <span class="current"><?= $p ?></span>
                    <?php else: ?>
                        <a href="/master/product.php?page=<?= $p ?>&keyword=<?= urlencode($_GET['keyword'] ?? '') ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <div class="slip-form">
            <h2 style="margin-bottom: 20px;"><?= $action === 'edit' ? '商品編集' : '商品登録' ?></h2>

            <form method="post" action="/master/product.php">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update' : 'create' ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="product_code">商品コード <span style="color: red;">*</span></label>
                        <input type="text" id="product_code" name="product_code" maxlength="15"
                               value="<?= htmlspecialchars($product['product_code'] ?? '') ?>" required
                               <?= $action === 'edit' ? 'readonly style="background: #f1f5f9;"' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label for="product_name">商品名 <span style="color: red;">*</span></label>
                        <input type="text" id="product_name" name="product_name" maxlength="40"
                               value="<?= htmlspecialchars($product['product_name'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="product_name_kana">商品名カナ <span style="color: red;">*</span></label>
                        <input type="text" id="product_name_kana" name="product_name_kana" maxlength="80"
                               value="<?= htmlspecialchars($product['product_name_kana'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="unit">単位</label>
                        <input type="text" id="unit" name="unit" maxlength="8"
                               value="<?= htmlspecialchars($product['unit'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="case_quantity">入数</label>
                        <input type="number" id="case_quantity" name="case_quantity"
                               value="<?= htmlspecialchars($product['case_quantity'] ?? 0) ?>">
                    </div>
                    <div class="form-group">
                        <label for="tax_category">課税区分 <span style="color: red;">*</span></label>
                        <select id="tax_category" name="tax_category" required>
                            <option value="1" <?= ($product['tax_category'] ?? 1) == 1 ? 'selected' : '' ?>>課税対象</option>
                            <option value="2" <?= ($product['tax_category'] ?? 1) == 2 ? 'selected' : '' ?>>非課税対象</option>
                            <option value="3" <?= ($product['tax_category'] ?? 1) == 3 ? 'selected' : '' ?>>課税対象外</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="reduced_tax_flag" value="1"
                                   <?= ($product['reduced_tax_flag'] ?? 0) ? 'checked' : '' ?>>
                            軽減税率対象品
                        </label>
                    </div>
                </div>

                <h2 style="margin: 24px 0 16px; padding-top: 24px; border-top: 1px solid #e2e8f0;">価格設定</h2>

                <div class="form-row">
                    <div class="form-group">
                        <label for="selling_price1_excl">売上単価1（税抜） <span style="color: red;">*</span></label>
                        <input type="number" id="selling_price1_excl" name="selling_price1_excl"
                               value="<?= htmlspecialchars($product['selling_price1_excl'] ?? 0) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="selling_price1_incl">売上単価1（税込）</label>
                        <input type="number" id="selling_price1_incl" name="selling_price1_incl"
                               value="<?= htmlspecialchars($product['selling_price1_incl'] ?? 0) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="selling_price2_excl">売上単価2（税抜）</label>
                        <input type="number" id="selling_price2_excl" name="selling_price2_excl"
                               value="<?= htmlspecialchars($product['selling_price2_excl'] ?? 0) ?>">
                    </div>
                    <div class="form-group">
                        <label for="selling_price2_incl">売上単価2（税込）</label>
                        <input type="number" id="selling_price2_incl" name="selling_price2_incl"
                               value="<?= htmlspecialchars($product['selling_price2_incl'] ?? 0) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="selling_price3_excl">売上単価3（税抜）</label>
                        <input type="number" id="selling_price3_excl" name="selling_price3_excl"
                               value="<?= htmlspecialchars($product['selling_price3_excl'] ?? 0) ?>">
                    </div>
                    <div class="form-group">
                        <label for="selling_price3_incl">売上単価3（税込）</label>
                        <input type="number" id="selling_price3_incl" name="selling_price3_incl"
                               value="<?= htmlspecialchars($product['selling_price3_incl'] ?? 0) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="selling_price4_excl">売上単価4（税抜）</label>
                        <input type="number" id="selling_price4_excl" name="selling_price4_excl"
                               value="<?= htmlspecialchars($product['selling_price4_excl'] ?? 0) ?>">
                    </div>
                    <div class="form-group">
                        <label for="selling_price4_incl">売上単価4（税込）</label>
                        <input type="number" id="selling_price4_incl" name="selling_price4_incl"
                               value="<?= htmlspecialchars($product['selling_price4_incl'] ?? 0) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="cost_price_excl">売上原価（税抜）</label>
                        <input type="number" id="cost_price_excl" name="cost_price_excl"
                               value="<?= htmlspecialchars($product['cost_price_excl'] ?? 0) ?>">
                    </div>
                    <div class="form-group">
                        <label for="cost_price_incl">売上原価（税込）</label>
                        <input type="number" id="cost_price_incl" name="cost_price_incl"
                               value="<?= htmlspecialchars($product['cost_price_incl'] ?? 0) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="remarks">備考</label>
                        <input type="text" id="remarks" name="remarks" maxlength="60"
                               value="<?= htmlspecialchars($product['remarks'] ?? '') ?>">
                    </div>
                </div>

                <div style="text-align: center; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">登録</button>
                    <a href="/master/product.php" class="btn btn-secondary">一覧に戻る</a>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
