<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';
require_once __DIR__ . '/../../Helpers/Csrf.php';

Session::start();
Auth::requireLogin();
Auth::requireFiscalYear();

$db = Database::getConnection();
$error = '';
$success = '';

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([Session::getUserId()]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $user_name = trim($_POST['user_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $currentPass = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';

    if ($user_name === '' || $email === '') {
        $error = 'ユーザ名とメールアドレスは必須です。';
    } else {
        $set = 'user_name = ?, email = ?';
        $params = [$user_name, $email];

        if ($newPass !== '') {
            if (!password_verify($currentPass, $user['password_hash'])) {
                $error = '現在のパスワードが正しくありません。';
            } elseif (strlen($newPass) < 4) {
                $error = '新しいパスワードは4文字以上で入力してください。';
            } else {
                $set .= ', password_hash = ?';
                $params[] = Auth::hashPassword($newPass);
            }
        }

        if (!$error) {
            $params[] = Session::getUserId();
            $stmt = $db->prepare("UPDATE users SET {$set} WHERE id = ?");
            $stmt->execute($params);
            $success = 'ユーザ情報を更新しました。';
            // 再取得
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([Session::getUserId()]);
            $user = $stmt->fetch();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><title>ユーザ情報変更 - 販売管理システム</title>
    <link rel="stylesheet" href="/SalesManagementSystem/public/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left"><a href="/SalesManagementSystem/" style="font-size:18px;color:#1e293b;">販売管理システム</a> <span style="margin-left:16px;color:#64748b;">ユーザ情報変更</span></div>
        <div class="header-right"><span class="fiscal-year"><?= htmlspecialchars(Session::get('fiscal_year_label')) ?>年度</span><a href="/SalesManagementSystem/" class="btn btn-small btn-secondary">TOP</a></div>
    </header>
    <main style="padding:24px;max-width:600px;margin:0 auto;">
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <div class="slip-form">
            <h2 style="margin-bottom:20px;">ユーザ情報変更</h2>
            <form method="post">
                <?= Csrf::field() ?>
                <div class="form-row"><div class="form-group"><label>ログインID</label><input type="text" value="<?= htmlspecialchars($user['login_id']) ?>" readonly style="background:#f1f5f9;"></div></div>
                <div class="form-row"><div class="form-group"><label>ユーザ名 *</label><input type="text" name="user_name" value="<?= htmlspecialchars($user['user_name']) ?>" required></div></div>
                <div class="form-row"><div class="form-group"><label>メールアドレス *</label><input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required></div></div>
                <h3 style="margin:20px 0 12px;padding-top:16px;border-top:1px solid #e2e8f0;">パスワード変更</h3>
                <div class="form-row"><div class="form-group"><label>現在のパスワード</label><input type="password" name="current_password"></div></div>
                <div class="form-row"><div class="form-group"><label>新しいパスワード</label><input type="password" name="new_password"></div></div>
                <div style="text-align:center;margin-top:24px;"><button type="submit" class="btn btn-primary">更新</button></div>
            </form>
        </div>
    </main>
</body>
</html>
