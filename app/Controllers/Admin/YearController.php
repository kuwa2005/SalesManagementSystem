<?php
require_once __DIR__ . '/../../Helpers/Session.php';
require_once __DIR__ . '/../../Helpers/Database.php';
require_once __DIR__ . '/../../Helpers/Auth.php';
require_once __DIR__ . '/../../Helpers/Csrf.php';

Session::start();
Auth::requireLogin();
Auth::requireFiscalYear();
Auth::requireAdmin();

$db = Database::getConnection();
$tenantId = Session::getTenantId();
$fiscalYearId = Session::getFiscalYearId();
$error = '';
$success = '';

// 現在の年度情報を取得
$stmt = $db->prepare("SELECT * FROM fiscal_years WHERE id = ?");
$stmt->execute([$fiscalYearId]);
$currentYear = $stmt->fetch();

// 翌年度が既に存在するかチェック
$nextYear = $currentYear['year_label'] + 1;
$stmt = $db->prepare("SELECT * FROM fiscal_years WHERE tenant_id = ? AND year_label = ?");
$stmt->execute([$tenantId, $nextYear]);
$nextYearExists = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'rollover') {
        if ($nextYearExists) {
            $error = '翌年度(' . $nextYear . '年度)は既に存在します。';
        } else {
            try {
                $db->beginTransaction();

                // 翌年度を追加
                $startDate = ($nextYear) . '-04-01';
                $endDate = ($nextYear + 1) . '-03-31';
                $stmt = $db->prepare("INSERT INTO fiscal_years (tenant_id, year_label, start_date, end_date, is_current) VALUES (?, ?, ?, ?, 0)");
                $stmt->execute([$tenantId, $nextYear, $startDate, $endDate]);
                $newYearId = $db->lastInsertId();

                // マスタ複製
                $tables = ['departments', 'staff', 'payment_types', 'descriptions', 'customers', 'categories', 'products'];
                foreach ($tables as $table) {
                    $stmt = $db->prepare("INSERT INTO {$table} (SELECT NULL, {$tenantId}, {$newYearId}," . str_repeat('?,', count($cols) - 2) . "?, ? FROM {$table} WHERE tenant_id = ? AND fiscal_year_id = ?)");
                    // 簡易版: 全カラムを取得してコピー
                    $cols = $db->query("SHOW COLUMNS FROM {$table}")->fetchAll(PDO::FETCH_COLUMN);
                    $cols = array_filter($cols, fn($c) => !in_array($c, ['id', 'tenant_id', 'fiscal_year_id', 'created_at', 'updated_at']));
                    $colList = implode(', ', array_merge(['tenant_id', 'fiscal_year_id'], $cols));

                    $placeholders = str_repeat('?,', count($cols) - 1) . '?';
                    $allParams = array_merge([$tenantId, $newYearId]);
                    $allParams[] = $tenantId;
                    $allParams[] = $fiscalYearId;

                    // データ取得
                    $dataStmt = $db->prepare("SELECT {$colList} FROM {$table} WHERE tenant_id = ? AND fiscal_year_id = ?");
                    $dataStmt->execute([$tenantId, $fiscalYearId]);
                    $rows = $dataStmt->fetchAll();

                    foreach ($rows as $row) {
                        $vals = array_values($row);
                        $insStmt = $db->prepare("INSERT INTO {$table} ({$colList}) VALUES (" . str_repeat('?,', count($vals) - 1) . "?)");
                        $insStmt->execute($vals);
                    }
                }

                // 得意先の期首売掛残高を設定
                $stmt = $db->prepare("
                    UPDATE customers c
                    SET c.opening_accounts_receivable = (
                        SELECT COALESCE(SUM(s.total_amount), 0) - COALESCE((SELECT SUM(p.total_amount) FROM payment_slips p WHERE p.tenant_id = c.tenant_id AND p.fiscal_year_id = c.fiscal_year_id AND p.customer_code = c.customer_code), 0)
                        FROM sales_slips s
                        WHERE s.tenant_id = c.tenant_id AND s.fiscal_year_id = ? AND s.customer_code = c.customer_code
                    )
                    WHERE c.tenant_id = ? AND c.fiscal_year_id = ?
                ");
                $stmt->execute([$fiscalYearId, $tenantId, $newYearId]);

                // 履歴記録
                $stmt = $db->prepare("INSERT INTO year_rollover_history (tenant_id, from_fiscal_year_id, to_fiscal_year_id, rollover_type, executed_by) VALUES (?, ?, ?, 1, ?)");
                $stmt->execute([$tenantId, $fiscalYearId, $newYearId, Session::getUserId()]);

                $db->commit();
                $success = $nextYear . '年度への繰越が完了しました。';
                // 翌年度情報を再取得
                $stmt = $db->prepare("SELECT * FROM fiscal_years WHERE id = ?");
                $stmt->execute([$newYearId]);
                $nextYearExists = $stmt->fetch();

            } catch (Exception $e) {
                $db->rollBack();
                $error = '繰越に失敗しました: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8"><title>年次繰越 - 販売管理システム</title>
    <link rel="stylesheet" href="/SalesManagementSystem/public/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="header-left"><a href="/SalesManagementSystem/" style="font-size:18px;color:#1e293b;">販売管理システム</a> <span style="margin-left:16px;color:#64748b;">年次繰越</span></div>
        <div class="header-right"><span class="fiscal-year"><?= htmlspecialchars(Session::get('fiscal_year_label')) ?>年度</span><a href="/SalesManagementSystem/" class="btn btn-small btn-secondary">TOP</a></div>
    </header>
    <main style="padding:24px;max-width:800px;margin:0 auto;">
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <div class="slip-form">
            <h2 style="margin-bottom:20px;">年次繰越</h2>

            <table style="margin-bottom:24px;">
                <tr><td style="padding:8px;">現在の年度</td><td style="padding:8px;font-weight:bold;"><?= htmlspecialchars($currentYear['year_label']) ?>年度</td></tr>
                <tr><td style="padding:8px;">期間</td><td style="padding:8px;"><?= htmlspecialchars($currentYear['start_date']) ?> ～ <?= htmlspecialchars($currentYear['end_date']) ?></td></tr>
                <tr><td style="padding:8px;">繰越先</td><td style="padding:8px;"><?= $nextYear ?>年度（<?= $nextYearExists ? '既に存在' : '未作成' ?>）</td></tr>
            </table>

            <?php if ($nextYearExists): ?>
                <div class="alert alert-info"><?= $nextYear ?>年度は既に作成済みです。</div>
            <?php else: ?>
                <p style="margin-bottom:16px;color:#64748b;">翌年度のマスタを複製し、得意先の期首売掛残高を設定します。</p>
                <p style="margin-bottom:16px;color:#dc2626;font-weight:bold;">※ この処理は取り消せません。</p>
                <form method="post" onsubmit="return confirm('本当に年次繰越を実行しますか？');">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="rollover">
                    <div style="text-align:center;">
                        <button type="submit" class="btn btn-primary">年次繰越を実行</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
