<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';
require_once __DIR__ . '/../../Helpers/Csrf.php';
require_once __DIR__ . '/../../Models/BaseModel.php';
require_once __DIR__ . '/../../Models/SalesSlip.php';

Session::start();
Auth::requireLogin();
Auth::requireFiscalYear();

$salesModel = new SalesSlip();
$error = '';
$success = '';

// POST処理（削除）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'delete') {
        $targetId = (int)($_POST['id'] ?? 0);
        if (!$salesModel->isUnbilled($targetId)) {
            $error = '請求締済みの伝票は削除できません。';
        } else {
            $salesModel->deleteWithDetails($targetId);
            $success = '売上伝票を削除しました。';
        }
    }
}

// 検索条件
$conditions = [];
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $conditions['keyword'] = $_GET['keyword'] ?? '';
    $conditions['customer_code'] = $_GET['customer_code'] ?? '';
    $conditions['date_from'] = $_GET['date_from'] ?? '';
    $conditions['date_to'] = $_GET['date_to'] ?? '';
    $conditions['status'] = $_GET['status'] ?? '';
}

$page = (int)($_GET['page'] ?? 1);
$slips = $salesModel->search($conditions, $page);

// 得意先一覧を取得
$db = Database::getConnection();
$stmt = $db->prepare("SELECT customer_code, customer_name FROM customers WHERE tenant_id = ? AND fiscal_year_id = ?");
$stmt->execute([Session::getTenantId(), Session::getFiscalYearId()]);
$customers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>売上伝票検索 - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <a href="/top.php" style="font-size: 18px; color: #1e293b;"><?= APP_NAME ?></a>
            <span style="margin-left: 16px; color: #64748b;">売上伝票検索</span>
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

        <div class="search-form">
            <form method="get" action="/sales/search.php">
                <div class="form-row">
                    <div class="form-group">
                        <label for="keyword">キーワード</label>
                        <input type="text" id="keyword" name="keyword" value="<?= htmlspecialchars($conditions['keyword'] ?? '') ?>" placeholder="伝票番号、得意先名">
                    </div>
                    <div class="form-group">
                        <label for="customer_code">得意先</label>
                        <select id="customer_code" name="customer_code">
                            <option value="">全て</option>
                            <?php foreach ($customers as $cust): ?>
                                <option value="<?= htmlspecialchars($cust['customer_code']) ?>" <?= ($conditions['customer_code'] ?? '') == $cust['customer_code'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cust['customer_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="date_from">日付 From</label>
                        <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($conditions['date_from'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_to">日付 To</label>
                        <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($conditions['date_to'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="status">状態</label>
                        <select id="status" name="status">
                            <option value="">全て</option>
                            <option value="0" <?= ($conditions['status'] ?? '') === '0' ? 'selected' : '' ?>>未請求</option>
                            <option value="1" <?= ($conditions['status'] ?? '') === '1' ? 'selected' : '' ?>>請求締済</option>
                        </select>
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
                <h2>売上伝票一覧</h2>
                <a href="/sales/input.php" class="btn btn-primary">新規登録</a>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>伝票番号</th>
                        <th>売上日</th>
                        <th>得意先</th>
                        <th class="text-right">税抜金額</th>
                        <th class="text-right">消費税</th>
                        <th class="text-right">税込合計</th>
                        <th>状態</th>
                        <th class="text-center">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($slips['data'])): ?>
                    <tr><td colspan="8" class="text-center">データがありません。</td></tr>
                    <?php else: ?>
                    <?php foreach ($slips['data'] as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['sales_slip_no']) ?></td>
                        <td><?= htmlspecialchars($row['sales_date']) ?></td>
                        <td><?= htmlspecialchars($row['customer_name'] ?? $row['customer_code']) ?></td>
                        <td class="text-right"><?= number_format($row['total_amount']) ?></td>
                        <td class="text-right"><?= number_format($row['tax_amount']) ?></td>
                        <td class="text-right"><?= number_format($row['total_amount'] + $row['tax_amount']) ?></td>
                        <td><?= $row['status'] == 0 ? '未請求' : '請求締済' ?></td>
                        <td class="text-center">
                            <a href="/sales/input.php?action=edit&id=<?= $row['id'] ?>" class="btn btn-small btn-secondary">編集</a>
                            <?php if ($row['status'] == 0): ?>
                            <form method="post" action="/sales/search.php" style="display: inline;" onsubmit="return confirm('本当に削除しますか？');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <button type="submit" class="btn btn-small btn-danger">削除</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($slips['total_pages'] > 1): ?>
            <div class="pagination">
                <?php for ($p = 1; $p <= $slips['total_pages']; $p++): ?>
                    <?php if ($p == $slips['page']): ?>
                        <span class="current"><?= $p ?></span>
                    <?php else: ?>
                        <a href="/sales/search.php?page=<?= $p ?>&keyword=<?= urlencode($conditions['keyword'] ?? '') ?>&customer_code=<?= urlencode($conditions['customer_code'] ?? '') ?>&date_from=<?= urlencode($conditions['date_from'] ?? '') ?>&date_to=<?= urlencode($conditions['date_to'] ?? '') ?>&status=<?= urlencode($conditions['status'] ?? '') ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
