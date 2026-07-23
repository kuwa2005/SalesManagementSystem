<?php
class Session {
    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function set(string $key, mixed $value): void {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed {
        return $_SESSION[$key] ?? $default;
    }

    public static function remove(string $key): void {
        unset($_SESSION[$key]);
    }

    public static function clear(): void {
        session_destroy();
        $_SESSION = [];
    }

    public static function flash(string $key, mixed $value): void {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public static function isLoggedIn(): bool {
        return self::get('user_id') !== null;
    }

    public static function getUserId(): ?int {
        return self::get('user_id');
    }

    public static function getTenantId(): ?int {
        return self::get('tenant_id');
    }

    public static function getFiscalYearId(): ?int {
        return self::get('fiscal_year_id');
    }

    public static function getUserRole(): ?int {
        return self::get('user_role');
    }

    public static function isAdmin(): bool {
        return self::getUserRole() === 0;
    }
}
