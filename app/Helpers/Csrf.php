<?php
class Csrf {
    public static function token(): string {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    public static function field(): string {
        return '<input type="hidden" name="_csrf_token" value="' . self::token() . '">';
    }

    public static function verify(): bool {
        $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return hash_equals(self::token(), $token);
    }

    public static function check(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !self::verify()) {
            http_response_code(403);
            exit('CSRFトークンが無効です。');
        }
    }
}
