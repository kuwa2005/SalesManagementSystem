-- 初期データ

-- テナント（デモ用）
INSERT INTO tenants (tenant_code, company_name, plan_type, max_users, max_sales_lines)
VALUES ('DEMO001', 'デモ株式会社', 1, 10, 999999);

-- 事業年度（2026年度）
INSERT INTO fiscal_years (tenant_id, year_label, start_date, end_date, is_current)
VALUES (1, '2026', '2026-04-01', '2027-03-31', 1);

-- 管理者・利用者ユーザは作成しない（初期パスワード等の認証情報は .env に記述）
-- 初期化は public/setup.php をブラウザで実行してください（.env の INITIAL_ADMIN_PASSWORD で作成されます）

-- 全権限を管理者に付与
INSERT INTO permissions (user_id, category_code, function_code, permission_level)
VALUES
(1, 'MST', '01', 2), (1, 'MST', '02', 2), (1, 'MST', '03', 2), (1, 'MST', '04', 2),
(1, 'MST', '05', 2), (1, 'MST', '06', 2), (1, 'MST', '07', 2), (1, 'MST', '08', 2),
(1, 'SAL', '01', 2), (1, 'SAL', '02', 2), (1, 'SAL', '03', 2), (1, 'SAL', '04', 2), (1, 'SAL', '05', 2),
(1, 'INV', '01', 2), (1, 'INV', '02', 2), (1, 'INV', '03', 2),
(1, 'PAY', '01', 2), (1, 'PAY', '02', 2), (1, 'PAY', '03', 2), (1, 'PAY', '04', 2),
(1, 'LED', '01', 2), (1, 'LED', '02', 2),
(1, 'RPT', '01', 2), (1, 'RPT', '02', 2), (1, 'RPT', '03', 2),
(1, 'ANA', '01', 2), (1, 'ANA', '02', 2), (1, 'ANA', '03', 2), (1, 'ANA', '04', 2),
(1, 'INQ', '01', 2), (1, 'INQ', '02', 2), (1, 'INQ', '03', 2),
(1, 'EXT', '01', 2),
(1, 'ADM', '01', 2), (1, 'ADM', '02', 2), (1, 'ADM', '03', 2), (1, 'ADM', '04', 2);

-- デフォルト税率（2026年度）
INSERT INTO tax_rates (tenant_id, fiscal_year_id, effective_date, standard_rate, reduced_rate)
VALUES (1, 1, '2019-10-01', 10.00, 8.00);

-- デフォルト入金区分
INSERT INTO payment_types (tenant_id, fiscal_year_id, payment_category, payment_type_code, payment_type_name)
VALUES
(1, 1, 1, '1000', '現金'),
(1, 1, 2, '2000', '銀行振込'),
(1, 1, 3, '9000', '振込手数料'),
(1, 1, 4, '3000', '手形');

-- デフォルト摘要
INSERT INTO descriptions (tenant_id, fiscal_year_id, description_type, description_code, description_name)
VALUES
(1, 1, 1, '0001', '通常取引'),
(1, 1, 2, '0001', '入金'),
(1, 1, 3, '0001', '請求'),
(1, 1, 4, '0001', '領収');
