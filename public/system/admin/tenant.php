<?php
require_once __DIR__ . '/../../app/Config/app.php';
require_once __DIR__ . '/../../app/Config/database.php';
require_once __DIR__ . '/../../app/Helpers/Session.php';
require_once __DIR__ . '/../../app/Helpers/Database.php';
require_once __DIR__ . '/../../app/Helpers/SuperAuth.php';
require_once __DIR__ . '/../../app/Helpers/Csrf.php';
require_once __DIR__ . '/../../app/Helpers/Validator.php';

Session::start();
SuperAuth::requireLogin();

$db = Database::getConnection();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create' || $postAction === 'update') {
        $data = [
            'tenant_code' => trim($_POST['tenant_code'] ?? ''),
            'company_name' => trim($_POST['company_name'] ?? ''),
            'plan_type' => (int)($_POST['plan_type'] ?? 0),
            'max_users' => (int)($_POST['max_users'] ?? 3),
            'max_sales_lines' => (int)($_POST['max_sales_lines'] ?? 1000),
        ];

        $v = new Validator($data);
        $v->required('tenant_code', 'テナントコード')
          ->maxLength('tenant_code', 20, 'テナントコード')
          ->required('company_name', '会社名');

        if ($v->hasErrors()) {
            $error = $v->getFirstError();
        } else {
            if ($postAction === 'create') {
                $existing = $db->prepare("SELECT id FROM tenants WHERE tenant_code = ?");
                $existing->execute([$data['tenant_code']]);
                if ($existing->fetch()) {
                    $error = 'このテナントコードは既に使用されています。';
                } else {
                    $data['created_at'] = date('Y-m-d H:i:s');
                    $fields = implode(', ', array_keys($data));
                    $placeholders = implode(', ', array_fill(0, count($data), '?'));
                    $db->prepare("INSERT INTO tenants ({$fields}) VALUES ({$placeholders})")->execute(array_values($data));
                    $success = 'テナントを追加しました。';
                    $action = 'list';
                }
            } else {
                $set = ['tenant_code = ?', 'company_name = ?', 'plan_type = ?', 'max_users = ?', 'max_sales_lines = ?', 'updated_at = NOW()'];
                $params = array_values($data);
                $params[] = $id;
                $db->prepare("UPDATE tenants SET " . implode(', ', $set) . " WHERE id = ?")->execute($params);
                $success = 'テナント情報を更新しました。';
                $action = 'list';
            }
        }
    }

    if ($postAction === 'delete') {
        $targetId = (int)($_POST['id'] ?? 0);
        // ユーザがいるかチェック
        $cnt = $db->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ?");
        $cnt->execute([$targetId]);
        if ($cnt->fetchColumn() > 0) {
            $error = 'テナントにユーザが存在するため削除できません。';
        } else {
            $db->prepare("DELETE FROM tenants WHERE id = ?")->execute([$targetId]);
            $success = 'テナントを削除しました。';
        }
        $action = 'list';
    }
}

$tenant = null;
$tenants = null;

if ($action === 'edit' && $id) {
    $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
    $stmt->execute([$id]);
    $tenant = $stmt->fetch();
} elseif ($action === 'detail' && $id) {
    $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
    $stmt->execute([$id]);
    $tenant = $stmt->fetch();

    // ユーザ一覧
    $usersStmt = $db->prepare("SELECT u.*, (SELECT COUNT(*) FROM sales_slips WHERE tenant_id = u.tenant_id) AS slip_count FROM users u WHERE u.tenant_id = ?");
    $usersStmt->execute([$id]);
    $tenantUsers = $usersStmt->fetchAll();

    // 年度一覧
    $yearStmt = $db->prepare("SELECT * FROM fiscal_years WHERE tenant_id = ?");
    $yearStmt->execute([$id]);
    $tenantYears = $yearStmt->fetchAll();
} else {
    $tenants = $db->query("SELECT t.*, (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id) AS user_count FROM tenants t ORDER BY t.created_at DESC")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><title>テナント管理 - システム管理</title>
    <link rel="stylesheet" href="/SalesManagementSystem/public/css/style.css">
    <style>
        body { background: #f0f2f5; }
        .admin-header { background: #1a1a2e; color: #fff; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; }
        .admin-header h1 { font-size: 16px; color: #e94560; }
        .admin-nav { display: flex; gap: 12px; align-items: center; }
        .admin-nav a { color: #aaa; text-decoration: none; font-size: 13px; }
        .admin-nav a:hover { color: #fff; }
        .content { padding: 24px; max-width: 1200px; margin: 0 auto; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>テナント管理</h1>
        <div class="admin-nav">
            <a href="index.php">ダッシュボード</a>
            <a href="tenant.php">テナント管理</a>
            <a href="/SalesManagementSystem/system/admin/logout.php">ログアウト</a>
        </div>
    </div>
    <div class="content">
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <?php if ($action === 'list'): ?>
        <div class="table-container">
            <div class="table-header"><h2>テナント一覧</h2><a href="?action=create" class="btn btn-primary">新規追加</a></div>
            <table>
                <thead><tr><th>コード</th><th>会社名</th><th>プラン</th><th class="text-right">ユーザ数</th><th class="text-right">上限</th><th>作成日</th><th class="text-center">操作</th></tr></thead>
                <tbody>
                <?php foreach ($tenants as $t): ?>
                <tr>
                    <td><?= htmlspecialchars($t['tenant_code']) ?></td>
                    <td><?= htmlspecialchars($t['company_name']) ?></td>
                    <td><?= $t['plan_type'] == 0 ? '<span style="color:#f59e0b;">無料版</span>' : '<span style="color:#16a34a;">有料版</span>' ?></td>
                    <td class="text-right"><?= $t['user_count'] ?></td>
                    <td class="text-right"><?= $t['max_users'] ?></td>
                    <td><?= htmlspecialchars($t['created_at']) ?></td>
                    <td class="text-center">
                        <a href="?action=detail&id=<?= $t['id'] ?>" class="btn btn-small btn-secondary">詳細</a>
                        <a href="?action=edit&id=<?= $t['id'] ?>" class="btn btn-small btn-secondary">編集</a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('本当に削除しますか？');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button type="submit" class="btn btn-small btn-danger">削除</button></form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php elseif ($action === 'detail' && $tenant): ?>
        <div class="table-container">
            <div class="table-header"><h2><?= htmlspecialchars($tenant['company_name']) ?> (<?= htmlspecialchars($tenant['tenant_code']) ?>)</h2><div><a href="?action=edit&id=<?= $tenant['id'] ?>" class="btn btn-secondary">編集</a> <a href="?action=list" class="btn btn-secondary">一覧に戻る</a></div></div>
            <table>
                <tr><th style="width:200px;">テナントコード</th><td><?= htmlspecialchars($tenant['tenant_code']) ?></td></tr>
                <tr><th>会社名</th><td><?= htmlspecialchars($tenant['company_name']) ?></td></tr>
                <tr><th>プラン</th><td><?= $tenant['plan_type'] == 0 ? '無料版' : '有料版' ?></td></tr>
                <tr><th>ユーザ上限</th><td><?= $tenant['max_users'] ?></td></tr>
                <tr><th>伝票行数上限</th><td><?= number_format($tenant['max_sales_lines']) ?></td></tr>
                <tr><th>作成日</th><td><?= htmlspecialchars($tenant['created_at']) ?></td></tr>
            </table>

            <h3 style="margin:24px 0 12px;">所属ユーザ</h3>
            <table>
                <thead><tr><th>ログインID</th><th>ユーザ名</th><th>メール</th><th>種別</th><th>状態</th></tr></thead>
                <tbody>
                <?php foreach ($tenantUsers as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['login_id']) ?></td><td><?= htmlspecialchars($u['user_name']) ?></td>
                    <td><?= htmlspecialchars($u['email'] ?? '') ?></td><td><?= $u['role_type'] == 0 ? '管理者' : '利用者' ?></td>
                    <td><?= $u['is_active'] ? '<span style="color:#16a34a;">有効</span>' : '<span style="color:#dc2626;">無効</span>' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h3 style="margin:24px 0 12px;">事業年度</h3>
            <table>
                <thead><tr><th>年度</th><th>期間</th><th>選択中</th></tr></thead>
                <tbody>
                <?php foreach ($tenantYears as $y): ?>
                <tr><td><?= htmlspecialchars($y['year_label']) ?>年度</td><td><?= htmlspecialchars($y['start_date']) ?> ～ <?= htmlspecialchars($y['end_date']) ?></td><td><?= $y['is_current'] ? '○' : '' ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php elseif ($action === 'create' || $action === 'edit'): ?>
        <div class="table-container">
            <h2 style="margin-bottom:20px;"><?= $action === 'edit' ? 'テナント編集' : 'テナント追加' ?></h2>
            <form method="post">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update' : 'create' ?>">
                <?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
                <table>
                    <tr><th style="width:200px;">テナントコード *</th><td><input type="text" name="tenant_code" value="<?= htmlspecialchars($tenant['tenant_code'] ?? '') ?>" maxlength="20" required <?= $action === 'edit' ? 'readonly style="background:#f1f5f9;"' : '' ?>></td></tr>
                    <tr><th>会社名 *</th><td><input type="text" name="company_name" value="<?= htmlspecialchars($tenant['company_name'] ?? '') ?>" maxlength="100" required></td></tr>
                    <tr><th>プラン</th><td><select name="plan_type"><option value="0" <?= ($tenant['plan_type'] ?? 0) == 0 ? 'selected' : '' ?>>無料版</option><option value="1" <?= ($tenant['plan_type'] ?? 0) == 1 ? 'selected' : '' ?>>有料版</option></select></td></tr>
                    <tr><th>ユーザ上限</th><td><input type="number" name="max_users" value="<?= $tenant['max_users'] ?? 3 ?>" min="1"></td></tr>
                    <tr><th>伝票行数上限</th><td><input type="number" name="max_sales_lines" value="<?= $tenant['max_sales_lines'] ?? 1000 ?>" min="0"></td></tr>
                </table>
                <div style="text-align:center;margin-top:24px;"><button type="submit" class="btn btn-primary">保存</button> <a href="?action=list" class="btn btn-secondary">キャンセル</a></div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
