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

-- スーパー管理者（パスワード: admin123）
INSERT INTO super_admins (admin_id, password_hash, admin_name) VALUES
('superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'システム管理者');
