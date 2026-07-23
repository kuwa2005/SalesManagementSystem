<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';
require_once __DIR__ . '/../../Helpers/Csrf.php';
require_once __DIR__ . '/../../Helpers/Validator.php';

Session::start();
Auth::requireLogin();
Auth::requireFiscalYear();
Auth::requireAdmin();

$db = Database::getConnection();
$tenantId = Session::getTenantId();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create' || $postAction === 'update') {
        $data = [
            'login_id' => trim($_POST['login_id'] ?? ''),
            'user_name' => trim($_POST['user_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'role_type' => (int)($_POST['role_type'] ?? 1),
        ];

        $validator = new Validator($data);
        $validator->required('login_id', 'ログインID')
                  ->required('user_name', 'ユーザ名');

        if ($postAction === 'create') {
            $password = $_POST['password'] ?? '';
            if (strlen($password) < 4) { $validator->required('password', 'パスワード（4文字以上）'); }
        }

        if ($validator->hasErrors()) {
            $error = $validator->getFirstError();
        } else {
            if ($postAction === 'create') {
                $data['password_hash'] = Auth::hashPassword($password);
                $data['tenant_id'] = $tenantId;
                $data['is_active'] = 1;
                $data['created_at'] = date('Y-m-d H:i:s');

                $fields = implode(', ', array_keys($data));
                $placeholders = implode(', ', array_fill(0, count($data), '?'));
                $stmt = $db->prepare("INSERT INTO users ({$fields}) VALUES ({$placeholders})");
                $stmt->execute(array_values($data));
                $success = 'ユーザを追加しました。';
            } else {
                $set = ['login_id = ?', 'user_name = ?', 'email = ?', 'role_type = ?'];
                $params = [$data['login_id'], $data['user_name'], $data['email'], $data['role_type'], $id];
                // パスワード変更
                if (!empty($_POST['password'])) {
                    $set[] = 'password_hash = ?';
                    $params = array_merge([Auth::hashPassword($_POST['password'])], $params);
                }
                $params[] = $id;
                array_unshift($params);
                array_pop($params);
                $stmt = $db->prepare("UPDATE users SET " . implode(', ', $set) . " WHERE id = ?");
                $stmt->execute(array_merge(
                    array_filter([$data['login_id'], $data['user_name'], $data['email'], $data['role_type']]),
                    !empty($_POST['password']) ? [Auth::hashPassword($_POST['password'])] : [],
                    [$id]
                ));
                $success = 'ユーザ情報を更新しました。';
            }
            $action = 'list';
        }
    }

    if ($postAction === 'delete') {
        $targetId = (int)($_POST['id'] ?? 0);
        if ($targetId === Session::getUserId()) {
            $error = '自分自身は削除できません。';
        } else {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$targetId, $tenantId]);
            $success = 'ユーザを削除しました。';
        }
        $action = 'list';
    }

    if ($postAction === 'reset_password') {
        $targetId = (int)($_POST['id'] ?? 0);
        $newPass = 'password';
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([Auth::hashPassword($newPass), $targetId]);
        $success = 'パスワードを初期化しました（初期パスワード: password）';
        $action = 'list';
    }
}

$user = null;
$users = null;

if ($action === 'edit' && $id) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenantId]);
    $user = $stmt->fetch();
} elseif ($action === 'list') {
    $stmt = $db->prepare("SELECT * FROM users WHERE tenant_id = ? ORDER BY login_id");
    $stmt->execute([$tenantId]);
    $users = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><title>ユーザ管理 - 販売管理システム</title>
    <link rel="stylesheet" href="/SalesManagementSystem/public/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left"><a href="/SalesManagementSystem/" style="font-size:18px;color:#1e293b;">販売管理システム</a> <span style="margin-left:16px;color:#64748b;">ユーザ管理</span></div>
        <div class="header-right"><span class="fiscal-year"><?= htmlspecialchars(Session::get('fiscal_year_label')) ?>年度</span><a href="/SalesManagementSystem/" class="btn btn-small btn-secondary">TOP</a></div>
    </header>
    <main style="padding:24px;max-width:900px;margin:0 auto;">
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <?php if ($action === 'list'): ?>
        <div class="table-container">
            <div class="table-header"><h2>ユーザ一覧</h2><a href="?action=create" class="btn btn-primary">新規追加</a></div>
            <table>
                <thead><tr><th>ログインID</th><th>ユーザ名</th><th>メール</th><th>種別</th><th class="text-center">操作</th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['login_id']) ?></td><td><?= htmlspecialchars($u['user_name']) ?></td>
                    <td><?= htmlspecialchars($u['email'] ?? '') ?></td><td><?= $u['role_type'] == 0 ? '管理者' : '利用者' ?></td>
                    <td class="text-center">
                        <a href="?action=edit&id=<?= $u['id'] ?>" class="btn btn-small btn-secondary">編集</a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('パスワードを初期化しますか？');"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button type="submit" class="btn btn-small btn-warning">PW初期化</button></form>
                        <?php if ($u['id'] != Session::getUserId()): ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('本当に削除しますか？');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button type="submit" class="btn btn-small btn-danger">削除</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="slip-form">
            <h2 style="margin-bottom:20px;"><?= $action === 'edit' ? 'ユーザ編集' : 'ユーザ追加' ?></h2>
            <form method="post">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update' : 'create' ?>">
                <div class="form-row">
                    <div class="form-group"><label>ログインID *</label><input type="text" name="login_id" value="<?= htmlspecialchars($user['login_id'] ?? '') ?>" required></div>
                    <div class="form-group"><label>ユーザ名 *</label><input type="text" name="user_name" value="<?= htmlspecialchars($user['user_name'] ?? '') ?>" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>メール</label><input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>"></div>
                    <div class="form-group"><label>種別</label><select name="role_type"><option value="1" <?= ($user['role_type'] ?? 1) == 1 ? 'selected' : '' ?>>利用者</option><option value="0" <?= ($user['role_type'] ?? 1) == 0 ? 'selected' : '' ?>>管理者</option></select></div>
                </div>
                <div class="form-row"><div class="form-group"><label>パスワード <?= $action === 'edit' ? '（変更時のみ入力）' : '*' ?></label><input type="password" name="password" <?= $action === 'create' ? 'required' : '' ?>></div></div>
                <div style="text-align:center;margin-top:24px;">
                    <button type="submit" class="btn btn-primary">登録</button>
                    <a href="?action=list" class="btn btn-secondary">一覧に戻る</a>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
