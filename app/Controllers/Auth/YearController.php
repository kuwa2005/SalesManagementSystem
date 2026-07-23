<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';

Session::start();
Auth::requireLogin();

$db = Database::getConnection();
$tenantId = Session::getTenantId();
$error = '';

// 年度一覧を取得
$stmt = $db->prepare("
    SELECT * FROM fiscal_years
    WHERE tenant_id = ?
    ORDER BY start_date DESC
    LIMIT " . MAX_FISCAL_YEARS . "
");
$stmt->execute([$tenantId]);
$fiscalYears = $stmt->fetchAll();

// POST処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fiscalYearId = (int)($_POST['fiscal_year_id'] ?? 0);

    if ($fiscalYearId > 0) {
        // 年度の存在確認
        $stmt = $db->prepare("
            SELECT * FROM fiscal_years
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$fiscalYearId, $tenantId]);
        $fy = $stmt->fetch();

        if ($fy) {
            Session::set('fiscal_year_id', $fiscalYearId);
            Session::set('fiscal_year_label', $fy['year_label']);
            header('Location: /SalesManagementSystem/');
            exit;
        }
    }
    $error = '有効な年度を選択してください。';
}

// 初回ログインチェック（基本情報未登録）
$stmt = $db->prepare("SELECT COUNT(*) FROM company_info WHERE tenant_id = ? AND fiscal_year_id = ?");
$stmt->execute([$tenantId, Session::get('fiscal_year_id') ?? 0]);
$hasCompanyInfo = $stmt->fetchColumn() > 0;

// 基本情報未登録の場合、初回年度を自動設定
if (empty($fiscalYears) && Session::getFiscalYearId() === null) {
    // 初回ログイン - 年度作成画面へ
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>年度選択 - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/SalesManagementSystem/public/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <h1>年度選択</h1>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (empty($fiscalYears)): ?>
            <p>利用可能な年度がありません。管理者にお問い合わせください。</p>
        <?php else: ?>
            <form method="post" action="/SalesManagementSystem/">
                <div class="form-group">
                    <label for="fiscal_year_id">事業年度を選択</label>
                    <select id="fiscal_year_id" name="fiscal_year_id" required>
                        <?php foreach ($fiscalYears as $fy): ?>
                            <option value="<?= $fy['id'] ?>">
                                <?= htmlspecialchars($fy['year_label']) ?>年度
                                （<?= htmlspecialchars($fy['start_date']) ?> ～ <?= htmlspecialchars($fy['end_date']) ?>）
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary btn-block">選択</button>
            </form>
        <?php endif; ?>

        <div class="login-links">
            <a href="/SalesManagementSystem/">ログイン画面に戻る</a>
        </div>
    </div>
</body>
</html>
