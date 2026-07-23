<?php
require_once __DIR__ . '/../../app/Config/app.php';
require_once __DIR__ . '/../../app/Config/database.php';
require_once __DIR__ . '/../../app/Helpers/Session.php';
require_once __DIR__ . '/../../app/Helpers/Database.php';
require_once __DIR__ . '/../../app/Helpers/SuperAuth.php';
require_once __DIR__ . '/../../app/Helpers/Csrf.php';

Session::start();

if (SuperAuth::isLoggedIn()) {
    header('Location: /SalesManagementSystem/system/admin/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $adminId = trim($_POST['admin_id'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($adminId === '' || $password === '') {
        $error = 'すべての項目を入力してください。';
    } elseif (SuperAuth::login($adminId, $password)) {
        header('Location: /SalesManagementSystem/system/admin/index.php');
        exit;
    } else {
        $error = '管理者IDまたはパスワードが正しくありません。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>システム管理 - ログイン</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; background: #1a1a2e; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .login-box { background: #16213e; padding: 40px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); width: 100%; max-width: 380px; }
        h1 { color: #e94560; text-align: center; margin-bottom: 8px; font-size: 20px; }
        .subtitle { color: #666; text-align: center; margin-bottom: 24px; font-size: 12px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; color: #aaa; margin-bottom: 6px; font-size: 13px; }
        input { width: 100%; padding: 10px 12px; border: 1px solid #333; border-radius: 4px; font-size: 14px; background: #0f3460; color: #fff; }
        input:focus { outline: none; border-color: #e94560; }
        button { width: 100%; padding: 12px; background: #e94560; color: #fff; border: none; border-radius: 4px; font-size: 14px; cursor: pointer; margin-top: 8px; }
        button:hover { background: #c73e54; }
        .error { background: #3d1521; border: 1px solid #e94560; color: #ff6b6b; padding: 10px; border-radius: 4px; margin-bottom: 16px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>システム管理</h1>
        <div class="subtitle">Sales Management System - Administration</div>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <?= Csrf::field() ?>
            <div class="form-group">
                <label for="admin_id">管理者ID</label>
                <input type="text" id="admin_id" name="admin_id" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">パスワード</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">ログイン</button>
        </form>
    </div>
</body>
</html>
