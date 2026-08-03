-- スーパー管理者テーブル
CREATE TABLE IF NOT EXISTS super_admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    admin_name VARCHAR(100) NOT NULL,
    is_active TINYINT NOT NULL DEFAULT 1,
    last_login_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_admin_id (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- スーパー管理者は初期データとして作成しない（認証情報は .env に記述）
-- 初期化は public/setup.php をブラウザで実行してください（.env の SUPER_ADMIN_PASSWORD で作成されます）
