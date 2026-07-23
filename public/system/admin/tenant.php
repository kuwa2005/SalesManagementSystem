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
$generatedPassword = '';

// ランダムパスワード生成
function generatePassword(int $length = 10): string {
    $chars = 'abcdefghijkmnpqrstuvwxyz23456789';
    $pw = '';
    for ($i = 0; $i < $length; $i++) {
        $pw .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $pw;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $postAction = $_POST['action'] ?? '';

    // テナント作成
    if ($postAction === 'create') {
        $tenantCode = trim($_POST['tenant_code'] ?? '');
        $companyName = trim($_POST['company_name'] ?? '');
        $planType = (int)($_POST['plan_type'] ?? 0);
        $maxUsers = (int)($_POST['max_users'] ?? 3);
        $maxLines = (int)($_POST['max_sales_lines'] ?? 1000);

        if ($tenantCode === '' || $companyName === '') {
            $error = 'テナントコードと会社名は必須です。';
        } else {
            $existing = $db->prepare("SELECT id FROM tenants WHERE tenant_code = ?");
            $existing->execute([$tenantCode]);
            if ($existing->fetch()) {
                $error = 'このテナントコードは既に使用されています。';
            } else {
                try {
                    $db->beginTransaction();

                    // テナント作成
                    $db->prepare("INSERT INTO tenants (tenant_code, company_name, plan_type, max_users, max_sales_lines, created_at) VALUES (?, ?, ?, ?, ?, NOW())")
                       ->execute([$tenantCode, $companyName, $planType, $maxUsers, $maxLines]);
                    $tenantId = $db->lastInsertId();

                    // 初期事業年度を作成
                    $year = date('Y');
                    $startDate = $year . '-04-01';
                    $endDate = ($year + 1) . '-03-31';
                    $db->prepare("INSERT INTO fiscal_years (tenant_id, year_label, start_date, end_date, is_current) VALUES (?, ?, ?, ?, 1)")
                       ->execute([$tenantId, $year, $startDate, $endDate]);

                    // 初期管理者ユーザを自動作成
                    $loginId = 'admin';
                    $userName = '管理者';
                    $generatedPassword = generatePassword();
                    $passwordHash = password_hash($generatedPassword, PASSWORD_DEFAULT);

                    $db->prepare("INSERT INTO users (tenant_id, login_id, password_hash, user_name, email, role_type, is_active, created_at) VALUES (?, ?, ?, ?, '', 0, 1, NOW())")
                       ->execute([$tenantId, $loginId, $passwordHash, $userName]);

                    $db->commit();

                    $success = 'テナントと初期管理者ユーザを作成しました。';
                    $action = 'created';
                    $id = $tenantId;

                } catch (Exception $e) {
                    $db->rollBack();
                    $error = '作成に失敗しました: ' . $e->getMessage();
                }
            }
        }
    }

    // テナント編集
    if ($postAction === 'update') {
        $db->prepare("UPDATE tenants SET company_name = ?, plan_type = ?, max_users = ?, max_sales_lines = ?, updated_at = NOW() WHERE id = ?")
           ->execute([
               trim($_POST['company_name'] ?? ''),
               (int)($_POST['plan_type'] ?? 0),
               (int)($_POST['max_users'] ?? 3),
               (int)($_POST['max_sales_lines'] ?? 1000),
               $id
           ]);
        $success = 'テナント情報を更新しました。';
        $action = 'detail';
    }

    // テナント削除
    if ($postAction === 'delete') {
        $targetId = (int)($_POST['id'] ?? 0);
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

    // ユーザ追加
    if ($postAction === 'add_user') {
        $targetTenantId = (int)($_POST['tenant_id'] ?? 0);
        $loginId = trim($_POST['login_id'] ?? '');
        $userName = trim($_POST['user_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $roleType = (int)($_POST['role_type'] ?? 1);
        $newPassword = $_POST['new_password'] ?? '';

        if ($loginId === '' || $userName === '' || $newPassword === '') {
            $error = 'ログインID、ユーザ名、パスワードは必須です。';
        } else {
            // 上限チェック
            $cnt = $db->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ?");
            $cnt->execute([$targetTenantId]);
            $currentCount = $cnt->fetchColumn();
            $tenantInfo = $db->prepare("SELECT max_users FROM tenants WHERE id = ?");
            $tenantInfo->execute([$targetTenantId]);
            $maxUsers = $tenantInfo->fetchColumn();

            if ($currentCount >= $maxUsers) {
                $error = "ユーザ上限（{$maxUsers}人）に達しています。";
            } else {
                // 重複チェック
                $dup = $db->prepare("SELECT id FROM users WHERE tenant_id = ? AND login_id = ?");
                $dup->execute([$targetTenantId, $loginId]);
                if ($dup->fetch()) {
                    $error = 'このログインIDは既に使用されています。';
                } else {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $db->prepare("INSERT INTO users (tenant_id, login_id, password_hash, user_name, email, role_type, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())")
                       ->execute([$targetTenantId, $loginId, $hash, $userName, $email, $roleType]);
                    $success = "ユーザ「{$loginId}」を追加しました。";
                    $action = 'detail';
                    $id = $targetTenantId;
                }
            }
        }
    }

    // パスワード変更
    if ($postAction === 'change_password') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $targetTenantId = (int)($_POST['tenant_id'] ?? 0);
        $resetPassword = $_POST['reset_password'] ?? '';

        if ($resetPassword === '') {
            $error = '新しいパスワードを入力してください。';
        } else {
            $hash = password_hash($resetPassword, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND tenant_id = ?")->execute([$hash, $userId, $targetTenantId]);
            $success = 'パスワードを変更しました。';
            $action = 'detail';
            $id = $targetTenantId;
        }
    }

    // ユーザ削除
    if ($postAction === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $targetTenantId = (int)($_POST['tenant_id'] ?? 0);

        // 管理者は最後の1人は削除不可
        $user = $db->prepare("SELECT role_type FROM users WHERE id = ? AND tenant_id = ?");
        $user->execute([$userId, $targetTenantId]);
        $userInfo = $user->fetch();

        if ($userInfo && $userInfo['role_type'] == 0) {
            $adminCount = $db->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND role_type = 0");
            $adminCount->execute([$targetTenantId]);
            if ($adminCount->fetchColumn() <= 1) {
                $error = '最後の管理者は削除できません。';
                $action = 'detail';
                $id = $targetTenantId;
            } else {
                $db->prepare("DELETE FROM users WHERE id = ? AND tenant_id = ?")->execute([$userId, $targetTenantId]);
                $success = 'ユーザを削除しました。';
                $action = 'detail';
                $id = $targetTenantId;
            }
        } else {
            $db->prepare("DELETE FROM users WHERE id = ? AND tenant_id = ?")->execute([$userId, $targetTenantId]);
            $success = 'ユーザを削除しました。';
            $action = 'detail';
            $id = $targetTenantId;
        }
    }
}

// データ取得
$tenant = null;
$tenants = null;
$tenantUsers = [];
$tenantYears = [];

if (($action === 'detail' || $action === 'created') && $id) {
    $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
    $stmt->execute([$id]);
    $tenant = $stmt->fetch();

    $usersStmt = $db->prepare("SELECT * FROM users WHERE tenant_id = ? ORDER BY role_type, login_id");
    $usersStmt->execute([$id]);
    $tenantUsers = $usersStmt->fetchAll();

    $yearStmt = $db->prepare("SELECT * FROM fiscal_years WHERE tenant_id = ?");
    $yearStmt->execute([$id]);
    $tenantYears = $yearStmt->fetchAll();
} elseif ($action === 'edit' && $id) {
    $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
    $stmt->execute([$id]);
    $tenant = $stmt->fetch();
} elseif ($action !== 'create') {
    $action = 'list';
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
        .password-box { background: #0f3460; color: #4ade80; padding: 16px; border-radius: 6px; margin: 16px 0; font-family: monospace; font-size: 18px; text-align: center; border: 2px dashed #4ade80; }
        .section-title { margin: 24px 0 12px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0; font-size: 16px; }
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

        <?php if ($action === 'created' && $generatedPassword): ?>
        <!-- 作成完了 - パスワード表示 -->
        <div class="table-container">
            <h2 style="margin-bottom:16px;">テナント作成完了</h2>
            <table>
                <tr><th style="width:200px;">テナントコード</th><td><?= htmlspecialchars($tenant['tenant_code']) ?></td></tr>
                <tr><th>会社名</th><td><?= htmlspecialchars($tenant['company_name']) ?></td></tr>
                <tr><th>プラン</th><td><?= $tenant['plan_type'] == 0 ? '無料版' : '有料版' ?></td></tr>
            </table>
            <h3 class="section-title">初期管理者アカウント</h3>
            <table>
                <tr><th style="width:200px;">ログインID</th><td><strong>admin</strong></td></tr>
                <tr><th>ユーザ名</th><td>管理者</td></tr>
                <tr><th>パスワード</th><td><div class="password-box"><?= htmlspecialchars($generatedPassword) ?></div><p style="color:#dc2626;font-size:12px;margin-top:8px;">※ このパスワードは画面を閉じると表示されません。必ずメモしてください。</p></td></tr>
                <tr><th>テナントログイン画面</th><td><a href="/SalesManagementSystem/" target="_blank">/SalesManagementSystem/</a></td></tr>
            </table>
            <div style="text-align:center;margin-top:24px;"><a href="?action=detail&id=<?= $tenant['id'] ?>" class="btn btn-primary">テナント詳細へ</a> <a href="?action=list" class="btn btn-secondary">一覧に戻る</a></div>
        </div>

        <?php elseif ($action === 'list'): ?>
        <!-- テナント一覧 -->
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
        <!-- テナント詳細 -->
        <div class="table-container">
            <div class="table-header"><h2><?= htmlspecialchars($tenant['company_name']) ?> (<?= htmlspecialchars($tenant['tenant_code']) ?>)</h2><div><a href="?action=edit&id=<?= $tenant['id'] ?>" class="btn btn-secondary">編集</a> <a href="?action=list" class="btn btn-secondary">一覧に戻る</a></div></div>
            <table>
                <tr><th style="width:200px;">テナントコード</th><td><?= htmlspecialchars($tenant['tenant_code']) ?></td></tr>
                <tr><th>会社名</th><td><?= htmlspecialchars($tenant['company_name']) ?></td></tr>
                <tr><th>プラン</th><td><?= $tenant['plan_type'] == 0 ? '無料版' : '有料版' ?></td></tr>
                <tr><th>ユーザ上限</th><td><?= $tenant['max_users'] ?>人</td></tr>
                <tr><th>伝票行数上限</th><td><?= number_format($tenant['max_sales_lines']) ?></td></tr>
                <tr><th>作成日</th><td><?= htmlspecialchars($tenant['created_at']) ?></td></tr>
            </table>
        </div>

        <!-- ユーザ一覧 -->
        <div class="table-container">
            <div class="table-header"><h3 class="section-title" style="margin:0;border:none;padding:0;">所属ユーザ（<?= count($tenantUsers) ?>/<?= $tenant['max_users'] ?>人）</h3></div>
            <table>
                <thead><tr><th>ログインID</th><th>ユーザ名</th><th>メール</th><th>種別</th><th>状態</th><th>最終ログイン</th><th class="text-center">操作</th></tr></thead>
                <tbody>
                <?php foreach ($tenantUsers as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['login_id']) ?></td>
                    <td><?= htmlspecialchars($u['user_name']) ?></td>
                    <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
                    <td><?= $u['role_type'] == 0 ? '<strong>管理者</strong>' : '利用者' ?></td>
                    <td><?= $u['is_active'] ? '<span style="color:#16a34a;">有効</span>' : '<span style="color:#dc2626;">無効</span>' ?></td>
                    <td><?= $u['last_login_at'] ? htmlspecialchars($u['last_login_at']) : '-' ?></td>
                    <td class="text-center">
                        <form method="post" style="display:inline;" onsubmit="return confirm('パスワードを変更しますか？');">
                            <input type="hidden" name="action" value="change_password_modal">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                        </form>
                        <a href="javascript:void(0)" onclick="document.getElementById('pw_<?= $u['id'] ?>').style.display='block'" class="btn btn-small btn-secondary">PW変更</a>
                        <?php if (count($tenantUsers) > 1 || $u['role_type'] != 0): ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('本当に削除しますか？');"><?= Csrf::field() ?><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>"><button type="submit" class="btn btn-small btn-danger">削除</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
                <!-- パスワード変更フォーム -->
                <tr id="pw_<?= $u['id'] ?>" style="display:none;background:#f8fafc;">
                    <td colspan="7">
                        <form method="post" style="display:flex;gap:8px;align-items:center;padding:8px;">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="change_password">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                            <strong><?= htmlspecialchars($u['login_id']) ?></strong> の新しいパスワード:
                            <input type="text" name="reset_password" placeholder="新しいパスワード" style="width:200px;" required>
                            <button type="submit" class="btn btn-small btn-primary">変更</button>
                            <a href="javascript:void(0)" onclick="document.getElementById('pw_<?= $u['id'] ?>').style.display='none'" class="btn btn-small btn-secondary">キャンセル</a>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <!-- ユーザ追加フォーム -->
            <?php if (count($tenantUsers) < $tenant['max_users']): ?>
            <div style="margin-top:16px;padding:16px;background:#f8fafc;border-radius:6px;">
                <h4 style="margin-bottom:12px;">ユーザ追加</h4>
                <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="add_user">
                    <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                    <div><label style="font-size:12px;display:block;">ログインID *</label><input type="text" name="login_id" required style="width:120px;"></div>
                    <div><label style="font-size:12px;display:block;">ユーザ名 *</label><input type="text" name="user_name" required style="width:120px;"></div>
                    <div><label style="font-size:12px;display:block;">メール</label><input type="email" name="email" style="width:160px;"></div>
                    <div><label style="font-size:12px;display:block;">種別</label><select name="role_type" style="width:100px;"><option value="1">利用者</option><option value="0">管理者</option></select></div>
                    <div><label style="font-size:12px;display:block;">パスワード *</label><input type="text" name="new_password" required style="width:140px;" placeholder="ランダム推奨"></div>
                    <button type="submit" class="btn btn-small btn-primary">追加</button>
                </form>
            </div>
            <?php else: ?>
            <div style="margin-top:16px;padding:12px;background:#fef2f2;border-radius:6px;color:#dc2626;font-size:13px;">ユーザ上限（<?= $tenant['max_users'] ?>人）に達しています。</div>
            <?php endif; ?>
        </div>

        <!-- 事業年度 -->
        <div class="table-container">
            <h3 class="section-title">事業年度</h3>
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
        <!-- テナント作成/編集 -->
        <div class="table-container">
            <h2 style="margin-bottom:20px;"><?= $action === 'edit' ? 'テナント編集' : 'テナント追加' ?></h2>
            <?php if ($action === 'create'): ?>
            <p style="margin-bottom:16px;color:#64748b;font-size:13px;">テナント作成時にログインID「admin」の初期管理者ユーザが自動作成されます。パスワードはランダム生成され、作成後に表示されます。</p>
            <?php endif; ?>
            <form method="post">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update' : 'create' ?>">
                <?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
                <table>
                    <tr><th style="width:200px;">テナントコード *</th><td><input type="text" name="tenant_code" value="<?= htmlspecialchars($tenant['tenant_code'] ?? '') ?>" maxlength="20" required <?= $action === 'edit' ? 'readonly style="background:#f1f5f9;"' : '' ?>></td></tr>
                    <tr><th>会社名 *</th><td><input type="text" name="company_name" value="<?= htmlspecialchars($tenant['company_name'] ?? '') ?>" maxlength="100" required></td></tr>
                    <tr><th>プラン</th><td><select name="plan_type"><option value="0" <?= ($tenant['plan_type'] ?? 0) == 0 ? 'selected' : '' ?>>無料版（上限3ユーザ、1000行）</option><option value="1" <?= ($tenant['plan_type'] ?? 0) == 1 ? 'selected' : '' ?>>有料版（無制限）</option></select></td></tr>
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
