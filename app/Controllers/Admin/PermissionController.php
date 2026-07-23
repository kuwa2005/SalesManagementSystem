<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';
require_once __DIR__ . '/../../Helpers/Csrf.php';

Session::start(); Auth::requireLogin(); Auth::requireFiscalYear(); Auth::requireAdmin();
$db = Database::getConnection(); $tenantId = Session::getTenantId();
$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $userId = (int)($_POST['user_id'] ?? 0);
    $permissions = $_POST['permissions'] ?? [];

    // 既存権限を削除
    $stmt = $db->prepare("DELETE FROM permissions WHERE user_id = ?");
    $stmt->execute([$userId]);

    // 新しい権限を登録
    foreach ($permissions as $cat => $funcs) {
        foreach ($funcs as $func => $level) {
            if ($level > 0) {
                $stmt = $db->prepare("INSERT INTO permissions (user_id, category_code, function_code, permission_level) VALUES (?, ?, ?, ?)");
                $stmt->execute([$userId, $cat, $func, $level]);
            }
        }
    }
    $success = '権限を更新しました。';
}

$userId = $_GET['user_id'] ?? '';
$users = $db->prepare("SELECT id, login_id, user_name, role_type FROM users WHERE tenant_id = ? ORDER BY login_id");
$users->execute([$tenantId]);
$userList = $users->fetchAll();

$userPerms = [];
if ($userId) {
    $stmt = $db->prepare("SELECT category_code, function_code, permission_level FROM permissions WHERE user_id = ?");
    $stmt->execute([$userId]);
    while ($row = $stmt->fetch()) { $userPerms[$row['category_code']][$row['function_code']] = $row['permission_level']; }
}

$categories = [
    'MST' => ['01'=>'基本情報','02'=>'部門','03'=>'担当者','04'=>'入金区分','05'=>'摘要','06'=>'得意先','07'=>'カテゴリー','08'=>'商品'],
    'SAL' => ['01'=>'伝票入力','02'=>'伝票訂正','03'=>'伝票複写','04'=>'赤伝','05'=>'伝票出力'],
    'INV' => ['01'=>'請求書作成','02'=>'再出力','03'=>'締解除'],
    'PAY' => ['01'=>'入金入力','02'=>'入金訂正','03'=>'実績一覧','04'=>'領収書'],
    'LED' => ['01'=>'得意先元帳','02'=>'売掛残高'],
    'RPT' => ['01'=>'売上明細','02'=>'日報','03'=>'月報'],
    'ANA' => ['01'=>'推移表','02'=>'順位表','03'=>'伸び率','04'=>'分析表'],
    'INQ' => ['01'=>'得意先照会','02'=>'商品照会','03'=>'所在地照会'],
    'EXT' => ['01'=>'会計CSV'],
    'ADM' => ['01'=>'年次繰越','02'=>'情報変更','03'=>'ユーザ管理','04'=>'権限管理'],
];
?>
<!DOCTYPE html>
<html lang="ja">
<head><meta charset="UTF-8"><title>権限管理 - 販売管理システム</title><link rel="stylesheet" href="/SalesManagementSystem/public/css/style.css"></head>
<body>
    <header class="main-header">
        <div class="header-left"><a href="/SalesManagementSystem/" style="font-size:18px;color:#1e293b;">販売管理システム</a> <span style="margin-left:16px;color:#64748b;">権限管理</span></div>
        <div class="header-right"><span class="fiscal-year"><?= htmlspecialchars(Session::get('fiscal_year_label')) ?>年度</span><a href="/SalesManagementSystem/" class="btn btn-small btn-secondary">TOP</a></div>
    </header>
    <main style="padding:24px;max-width:1200px;margin:0 auto;">
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <div class="search-form">
            <form method="get"><div class="form-row">
                <div class="form-group"><label>ユーザ</label><select name="user_id"><option value="">-- 選択 --</option><?php foreach ($userList as $u): ?><option value="<?= $u['id'] ?>" <?= $userId == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['login_id'] . ' - ' . $u['user_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group" style="flex:0;"><label>&nbsp;</label><button type="submit" class="btn btn-primary">選択</button></div>
            </div></form>
        </div>

        <?php if ($userId && $userId != Session::getUserId()): ?>
        <div class="table-container">
            <form method="post">
                <?= Csrf::field() ?>
                <input type="hidden" name="user_id" value="<?= htmlspecialchars($userId) ?>">
                <h2 style="margin-bottom:16px;">権限設定</h2>
                <?php foreach ($categories as $catCode => $funcs): ?>
                <h3 style="margin:16px 0 8px;padding:8px;background:#f1f5f9;"><?= htmlspecialchars($catCode) ?></h3>
                <table style="width:100%;">
                    <thead><tr><th>機能</th><th class="text-center" style="width:120px;">不許可</th><th class="text-center" style="width:120px;">一部許可</th><th class="text-center" style="width:120px;">全許可</th></tr></thead>
                    <tbody>
                    <?php foreach ($funcs as $funcCode => $funcName): ?>
                    <?php $current = $userPerms[$catCode][$funcCode] ?? 0; ?>
                    <tr>
                        <td><?= htmlspecialchars($funcName) ?></td>
                        <td class="text-center"><input type="radio" name="permissions[<?= $catCode ?>][<?= $funcCode ?>]" value="0" <?= $current == 0 ? 'checked' : '' ?>></td>
                        <td class="text-center"><input type="radio" name="permissions[<?= $catCode ?>][<?= $funcCode ?>]" value="1" <?= $current == 1 ? 'checked' : '' ?>></td>
                        <td class="text-center"><input type="radio" name="permissions[<?= $catCode ?>][<?= $funcCode ?>]" value="2" <?= $current == 2 ? 'checked' : '' ?>></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endforeach; ?>
                <div style="text-align:center;margin-top:24px;"><button type="submit" class="btn btn-primary">保存</button></div>
            </form>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
