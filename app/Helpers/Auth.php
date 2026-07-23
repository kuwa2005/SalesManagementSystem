<?php
class Auth {
    public static function login(string $tenantCode, string $loginId, string $password): ?array {
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT u.*, t.id as tenant_id, t.tenant_code
            FROM users u
            JOIN tenants t ON u.tenant_id = t.id
            WHERE t.tenant_code = ? AND u.login_id = ? AND u.is_active = 1
        ");
        $stmt->execute([$tenantCode, $loginId]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // ログイン成功
            Session::set('user_id', $user['id']);
            Session::set('tenant_id', $user['tenant_id']);
            Session::set('user_name', $user['user_name']);
            Session::set('user_role', $user['role_type']);
            Session::set('login_id', $user['login_id']);

            // 最終ログイン時刻更新
            $stmt = $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);

            return $user;
        }

        return null;
    }

    public static function logout(): void {
        Session::clear();
    }

    public static function requireLogin(): void {
        Session::start();
        if (!Session::isLoggedIn()) {
            header('Location: /login.php');
            exit;
        }
    }

    public static function requireFiscalYear(): void {
        if (Session::getFiscalYearId() === null) {
            header('Location: /year-select.php');
            exit;
        }
    }

    public static function requireAdmin(): void {
        if (!Session::isAdmin()) {
            header('Location: /top.php');
            exit;
        }
    }

    public static function hasPermission(string $category, string $function = ''): bool {
        if (Session::isAdmin()) {
            return true;
        }

        $db = Database::getConnection();
        $userId = Session::getUserId();

        if (empty($function)) {
            $stmt = $db->prepare("
                SELECT permission_level FROM permissions
                WHERE user_id = ? AND category_code = ?
            ");
            $stmt->execute([$userId, $category]);
        } else {
            $stmt = $db->prepare("
                SELECT permission_level FROM permissions
                WHERE user_id = ? AND category_code = ? AND function_code = ?
            ");
            $stmt->execute([$userId, $category, $function]);
        }

        $result = $stmt->fetch();
        return $result && $result['permission_level'] > 0;
    }

    public static function getPermissions(): array {
        if (Session::isAdmin()) {
            return ['*' => 2]; // 全権限
        }

        $db = Database::getConnection();
        $userId = Session::getUserId();

        $stmt = $db->prepare("
            SELECT category_code, function_code, permission_level
            FROM permissions WHERE user_id = ?
        ");
        $stmt->execute([$userId]);

        $permissions = [];
        while ($row = $stmt->fetch()) {
            $permissions[$row['category_code']][$row['function_code']] = $row['permission_level'];
        }
        return $permissions;
    }

    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
