<?php
class SuperAuth {
    public static function login(string $adminId, string $password): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM super_admins WHERE admin_id = ? AND is_active = 1");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            Session::set('super_admin_id', $admin['id']);
            Session::set('super_admin_name', $admin['admin_name']);
            $db->prepare("UPDATE super_admins SET last_login_at = NOW() WHERE id = ?")->execute([$admin['id']]);
            return true;
        }
        return false;
    }

    public static function isLoggedIn(): bool {
        return Session::get('super_admin_id') !== null;
    }

    public static function requireLogin(): void {
        if (!self::isLoggedIn()) {
            header('Location: /system/admin/login.php');
            exit;
        }
    }

    public static function logout(): void {
        Session::remove('super_admin_id');
        Session::remove('super_admin_name');
    }
}
