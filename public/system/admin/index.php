<?php
require_once __DIR__ . '/../../app/Config/app.php';
require_once __DIR__ . '/../../app/Config/database.php';
require_once __DIR__ . '/../../app/Helpers/Session.php';
require_once __DIR__ . '/../../app/Helpers/Database.php';
require_once __DIR__ . '/../../app/Helpers/SuperAuth.php';

Session::start();
SuperAuth::requireLogin();

$db = Database::getConnection();

// 統計情報
$tenants = $db->query("SELECT COUNT(*) FROM tenants")->fetchColumn();
$users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$activeTenants = $db->query("SELECT COUNT(DISTINCT tenant_id) FROM users WHERE is_active = 1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><title>システム管理 - ダッシュボード</title>
    <link rel="stylesheet" href="/SalesManagementSystem/public/css/style.css">
    <style>
        body { background: #f0f2f5; }
        .admin-header { background: #1a1a2e; color: #fff; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; }
        .admin-header h1 { font-size: 16px; color: #e94560; }
        .admin-nav { display: flex; gap: 12px; align-items: center; }
        .admin-nav a { color: #aaa; text-decoration: none; font-size: 13px; }
        .admin-nav a:hover { color: #fff; }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; padding: 24px; max-width: 1000px; margin: 0 auto; }
        .stat-card { background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); text-align: center; }
        .stat-card .number { font-size: 36px; font-weight: bold; color: #1a1a2e; }
        .stat-card .label { color: #666; margin-top: 8px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>システム管理パネル</h1>
        <div class="admin-nav">
            <span style="color:#666;font-size:13px;">管理者: <?= htmlspecialchars(Session::get('super_admin_name')) ?></span>
            <a href="tenant.php">テナント管理</a>
            <a href="/SalesManagementSystem/system/admin/logout.php">ログアウト</a>
        </div>
    </div>
    <div class="stats">
        <div class="stat-card"><div class="number"><?= $tenants ?></div><div class="label">テナント数</div></div>
        <div class="stat-card"><div class="number"><?= $activeTenants ?></div><div class="label">アクティブテナント</div></div>
        <div class="stat-card"><div class="number"><?= $users ?></div><div class="label">総ユーザ数</div></div>
    </div>
</body>
</html>
