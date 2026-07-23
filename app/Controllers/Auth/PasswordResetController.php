<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Csrf.php';

Session::start();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();

    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = 'メールアドレスを入力してください。';
    } else {
        // メールアドレスの存在確認
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            // 実際にはメール送信を行う
            $message = 'パスワード再発行の案内メールを送信しました。';
        } else {
            // セキュリティ上、存在しない場合も同じメッセージを表示
            $message = 'パスワード再発行の案内メールを送信しました。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>パスワード再発行 - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/SalesManagementSystem/public/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <h1>パスワード再発行</h1>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!$message): ?>
        <form method="post" action="/password-reset.php">
            <?= Csrf::field() ?>

            <p style="margin-bottom: 20px; font-size: 13px; color: #64748b;">
                登録済みのメールアドレスを入力してください。<br>
                パスワード再発行の案内メールを送信します。
            </p>

            <div class="form-group">
                <label for="email">メールアドレス</label>
                <input type="email" id="email" name="email" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">送信</button>
        </form>
        <?php endif; ?>

        <div class="login-links">
            <a href="/login.php">ログイン画面に戻る</a>
        </div>
    </div>
</body>
</html>
