<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';
require_once __DIR__ . '/../../Helpers/Csrf.php';

Session::start();

// 既にログイン済み
if (Session::isLoggedIn()) {
    if (Session::getFiscalYearId()) {
        header('Location: /SalesManagementSystem/');
    } else {
        header('Location: /SalesManagementSystem/');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();

    $tenantCode = trim($_POST['tenant_code'] ?? '');
    $loginId = trim($_POST['login_id'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($tenantCode === '' || $loginId === '' || $password === '') {
        $error = 'すべての項目を入力してください。';
    } else {
        $user = Auth::login($tenantCode, $loginId, $password);
        if ($user) {
            header('Location: /SalesManagementSystem/');
            exit;
        } else {
            $error = '契約者ID、ログインID、パスワードが正しくありません。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/SalesManagementSystem/public/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <h1><?= APP_NAME ?></h1>
        <form method="post" action="/SalesManagementSystem/">
            <?= Csrf::field() ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="form-group">
                <label for="tenant_code">契約者ID</label>
                <input type="text" id="tenant_code" name="tenant_code"
                       value="<?= htmlspecialchars($tenantCode ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="login_id">ログインID</label>
                <input type="text" id="login_id" name="login_id"
                       value="<?= htmlspecialchars($loginId ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="password">パスワード</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">ログイン</button>
        </form>

        <div class="login-links">
            <a href="/SalesManagementSystem/password-reset.php">パスワードをお忘れですか？</a>
        </div>
    </div>
</body>
</html>
