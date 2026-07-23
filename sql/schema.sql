-- 販売管理システム スキーマ定義
-- 対応: MySQL 8.0+

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- テナント（契約者）
-- =====================================================
CREATE TABLE tenants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_code VARCHAR(20) NOT NULL,
    company_name VARCHAR(100),
    plan_type TINYINT NOT NULL DEFAULT 0 COMMENT '0=無料版, 1=有料版',
    max_users INT NOT NULL DEFAULT 3,
    max_sales_lines INT NOT NULL DEFAULT 1000,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tenant_code (tenant_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 事業年度
-- =====================================================
CREATE TABLE fiscal_years (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    year_label VARCHAR(10) NOT NULL COMMENT '例: 2026',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_current TINYINT NOT NULL DEFAULT 0,
    rolled_over TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    UNIQUE KEY uk_tenant_year (tenant_id, year_label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- ユーザ
-- =====================================================
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    login_id VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    user_name VARCHAR(50) NOT NULL,
    email VARCHAR(100),
    role_type TINYINT NOT NULL DEFAULT 1 COMMENT '0=管理者, 1=利用者',
    is_active TINYINT NOT NULL DEFAULT 1,
    last_login_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    UNIQUE KEY uk_tenant_login (tenant_id, login_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 権限
-- =====================================================
CREATE TABLE permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    category_code VARCHAR(20) NOT NULL COMMENT 'MST, SAL, INV, PAY, LED, RPT, ANA, INQ, EXT, ADM',
    function_code VARCHAR(20) NOT NULL COMMENT '機能コード',
    permission_level TINYINT NOT NULL DEFAULT 0 COMMENT '0=不許可, 1=一部許可, 2=全機能許可',
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY uk_user_permission (user_id, category_code, function_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 基本情報（会社情報+初期設定）
-- =====================================================
CREATE TABLE company_info (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    fiscal_year_id INT UNSIGNED NOT NULL,
    -- 会社情報
    company_name VARCHAR(40) NOT NULL,
    company_name_kana VARCHAR(40),
    postal_code CHAR(7) NOT NULL,
    address1 VARCHAR(40) NOT NULL COMMENT '都道府県',
    address2 VARCHAR(40) NOT NULL COMMENT '市区町村・町域',
    address3 VARCHAR(40),
    tel VARCHAR(20) NOT NULL,
    fax VARCHAR(20),
    email VARCHAR(100),
    homepage VARCHAR(100),
    invoice_registration_number VARCHAR(13) COMMENT '適格請求書発行事業者登録番号',
    -- 初期設定
    slip_numbering_method TINYINT NOT NULL DEFAULT 1 COMMENT '1=自動(年度), 2=自動(月度), 3=手入力',
    invoice_numbering_method TINYINT NOT NULL DEFAULT 1,
    unit_price_fraction_method TINYINT NOT NULL DEFAULT 1 COMMENT '1=切捨て, 2=切上げ, 3=四捨五入',
    quantity_decimal_places TINYINT NOT NULL DEFAULT 0,
    amount_fraction_method TINYINT NOT NULL DEFAULT 1,
    cost_price_decimal_places TINYINT NOT NULL DEFAULT 0,
    selling_price_decimal_places TINYINT NOT NULL DEFAULT 0,
    fiscal_month INT NOT NULL COMMENT '決算月 1-12',
    closing_day TINYINT NOT NULL COMMENT '自社締日 1-27, 31=月末',
    company_name_print TINYINT NOT NULL DEFAULT 1,
    department_address_print TINYINT NOT NULL DEFAULT 0,
    address_print TINYINT NOT NULL DEFAULT 1,
    stamp_print TINYINT NOT NULL DEFAULT 0,
    stamp_image VARCHAR(255),
    selling_price1_name VARCHAR(20) DEFAULT '売上単価1',
    selling_price2_name VARCHAR(20) DEFAULT '売上単価2',
    selling_price3_name VARCHAR(20) DEFAULT '売上単価3',
    selling_price4_name VARCHAR(20) DEFAULT '売上単価4',
    bank_info1 VARCHAR(40),
    bank_info2 VARCHAR(40),
    bank_info3 VARCHAR(40),
    invoice_title VARCHAR(9) DEFAULT '請求書',
    payment_term VARCHAR(25),
    remarks VARCHAR(60),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id),
    UNIQUE KEY uk_tenant_fiscal (tenant_id, fiscal_year_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 自社部門マスタ
-- =====================================================
CREATE TABLE departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    fiscal_year_id INT UNSIGNED NOT NULL,
    department_code VARCHAR(14) NOT NULL,
    department_name VARCHAR(40) NOT NULL,
    department_name_kana VARCHAR(80) NOT NULL,
    department_name_short VARCHAR(14) NOT NULL,
    postal_code CHAR(7) NOT NULL,
    address1 VARCHAR(40) NOT NULL,
    address2 VARCHAR(40) NOT NULL,
    address3 VARCHAR(40),
    tel VARCHAR(20) NOT NULL,
    fax VARCHAR(20),
    name_print TINYINT NOT NULL DEFAULT 0,
    remarks VARCHAR(60),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id),
    UNIQUE KEY uk_dept_code (tenant_id, fiscal_year_id, department_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 自社担当者マスタ
-- =====================================================
CREATE TABLE staff (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    fiscal_year_id INT UNSIGNED NOT NULL,
    staff_code VARCHAR(8) NOT NULL,
    staff_name VARCHAR(20) NOT NULL,
    staff_name_kana VARCHAR(60),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id),
    UNIQUE KEY uk_staff_code (tenant_id, fiscal_year_id, staff_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 入金区分マスタ
-- =====================================================
CREATE TABLE payment_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    fiscal_year_id INT UNSIGNED NOT NULL,
    payment_category TINYINT NOT NULL COMMENT '1=現金, 2=振込, 3=手数料, 4=手形',
    payment_type_code VARCHAR(4) NOT NULL,
    payment_type_name VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id),
    UNIQUE KEY uk_payment_type_code (tenant_id, fiscal_year_id, payment_type_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 摘要マスタ
-- =====================================================
CREATE TABLE descriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    fiscal_year_id INT UNSIGNED NOT NULL,
    description_type TINYINT NOT NULL COMMENT '1=売上伝票, 2=入金伝票, 3=請求書, 4=領収書',
    description_code VARCHAR(4) NOT NULL,
    description_name VARCHAR(40) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id),
    UNIQUE KEY uk_desc_code (tenant_id, fiscal_year_id, description_type, description_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 得意先マスタ
-- =====================================================
CREATE TABLE customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    fiscal_year_id INT UNSIGNED NOT NULL,
    customer_code VARCHAR(14) NOT NULL,
    customer_name VARCHAR(40) NOT NULL,
    customer_name_kana VARCHAR(80) NOT NULL,
    customer_name_short VARCHAR(14),
    customer_honorific VARCHAR(4) NOT NULL DEFAULT '御中',
    postal_code CHAR(7) NOT NULL,
    address1 VARCHAR(40) NOT NULL,
    address2 VARCHAR(40) NOT NULL,
    address3 VARCHAR(40),
    tel VARCHAR(20),
    fax VARCHAR(20),
    email VARCHAR(100),
    homepage VARCHAR(100),
    customer_staff_name VARCHAR(20),
    staff_honorific VARCHAR(5) DEFAULT '様',
    department_name VARCHAR(40),
    position_name VARCHAR(40),
    price_type TINYINT NOT NULL DEFAULT 1 COMMENT '1-4=売上単価, 5=原価',
    tax_fraction_method TINYINT NOT NULL DEFAULT 1 COMMENT '1=切捨て, 2=切上げ, 3=四捨五入',
    tax_processing TINYINT NOT NULL DEFAULT 1 COMMENT '1-6: 外税/伝票計 etc',
    billing_method TINYINT NOT NULL DEFAULT 0 COMMENT '0=締め請求, 1=都度請求',
    closing_day TINYINT COMMENT '1-27, 31=月末',
    slip_type_settings VARCHAR(100) COMMENT '伝票種別設定 JSON',
    opening_accounts_receivable DECIMAL(11,0) DEFAULT 0,
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),
    remarks VARCHAR(60),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id),
    UNIQUE KEY uk_customer_code (tenant_id, fiscal_year_id, customer_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 商品カテゴリーマスタ（大/中/小分類）
-- =====================================================
CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    fiscal_year_id INT UNSIGNED NOT NULL,
    large_code VARCHAR(8),
    large_name VARCHAR(14),
    medium_code VARCHAR(14),
    medium_name VARCHAR(40),
    small_code VARCHAR(14),
    small_name VARCHAR(40),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id),
    UNIQUE KEY uk_category (tenant_id, fiscal_year_id, large_code, medium_code, small_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 商品マスタ
-- =====================================================
CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    fiscal_year_id INT UNSIGNED NOT NULL,
    product_code VARCHAR(15) NOT NULL,
    product_name VARCHAR(40) NOT NULL,
    product_name_kana VARCHAR(80) NOT NULL,
    large_category_code VARCHAR(8),
    medium_category_code VARCHAR(14),
    small_category_code VARCHAR(14),
    unit VARCHAR(8),
    case_quantity INT DEFAULT 0,
    tax_category TINYINT NOT NULL DEFAULT 1 COMMENT '1=課税, 2=非課税, 3=対象外',
    reduced_tax_flag TINYINT NOT NULL DEFAULT 0,
    selling_price1_excl DECIMAL(9,0),
    selling_price1_incl DECIMAL(9,0),
    selling_price2_excl DECIMAL(9,0),
    selling_price2_incl DECIMAL(9,0),
    selling_price3_excl DECIMAL(9,0),
    selling_price3_incl DECIMAL(9,0),
    selling_price4_excl DECIMAL(9,0),
    selling_price4_incl DECIMAL(9,0),
    cost_price_excl DECIMAL(9,0),
    cost_price_incl DECIMAL(9,0),
    remarks VARCHAR(60),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id),
    UNIQUE KEY uk_product_code (tenant_id, fiscal_year_id, product_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 売上伝票ヘッダ
-- =====================================================
CREATE TABLE sales_slips (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    fiscal_year_id INT UNSIGNED NOT NULL,
    sales_slip_no VARCHAR(10) NOT NULL,
    sales_date DATE NOT NULL,
    department_code VARCHAR(14),
    staff_code VARCHAR(8),
    customer_code VARCHAR(14) NOT NULL,
    delivery_customer_code VARCHAR(14) NOT NULL,
    tax_processing TINYINT NOT NULL,
    price_type TINYINT NOT NULL,
    invoice_no VARCHAR(10),
    invoice_close_date DATE,
    total_amount DECIMAL(12,0) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(12,0) NOT NULL DEFAULT 0,
    gross_profit DECIMAL(12,0) NOT NULL DEFAULT 0,
    status TINYINT NOT NULL DEFAULT 0 COMMENT '0=未請求, 1=請求締済',
    output_status_slip TINYINT NOT NULL DEFAULT 0,
    output_status_delivery TINYINT NOT NULL DEFAULT 0,
    output_status_receipt TINYINT NOT NULL DEFAULT 0,
    output_status_invoice TINYINT NOT NULL DEFAULT 0,
    remarks VARCHAR(40),
    staff_print TINYINT NOT NULL DEFAULT 0,
    invoice_remarks_print TINYINT NOT NULL DEFAULT 0,
    created_by INT UNSIGNED,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id),
    UNIQUE KEY uk_sales_slip_no (tenant_id, fiscal_year_id, sales_slip_no),
    INDEX idx_sales_date (tenant_id, fiscal_year_id, sales_date),
    INDEX idx_customer (tenant_id, fiscal_year_id, customer_code),
    INDEX idx_status (tenant_id, fiscal_year_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 売上伝票明細
-- =====================================================
CREATE TABLE sales_details (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sales_slip_id INT UNSIGNED NOT NULL,
    line_no SMALLINT UNSIGNED NOT NULL,
    breakdown_type TINYINT NOT NULL DEFAULT 1 COMMENT '1=通常, 2=値引き, 3=返品, 4=消費税, 5=摘要',
    product_code VARCHAR(15),
    product_name VARCHAR(40),
    unit VARCHAR(8),
    case_quantity DECIMAL(9,0) DEFAULT 0,
    quantity DECIMAL(17,4) NOT NULL DEFAULT 0,
    cost_price DECIMAL(9,0) DEFAULT 0,
    unit_price DECIMAL(9,0) NOT NULL DEFAULT 0,
    amount DECIMAL(12,0) NOT NULL DEFAULT 0,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    remarks VARCHAR(40),
    FOREIGN KEY (sales_slip_id) REFERENCES sales_slips(id) ON DELETE CASCADE,
    UNIQUE KEY uk_sales_detail_line (sales_slip_id, line_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 請求書
-- =====================================================
CREATE TABLE invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    fiscal_year_id INT UNSIGNED NOT NULL,
    invoice_no VARCHAR(10) NOT NULL,
    invoice_date DATE NOT NULL,
    customer_code VARCHAR(14) NOT NULL,
    staff_code VARCHAR(8),
    department_code VARCHAR(14),
    previous_amount DECIMAL(12,0) NOT NULL DEFAULT 0 COMMENT '前回御請求額',
    payment_amount DECIMAL(12,0) NOT NULL DEFAULT 0 COMMENT '御入金額',
    carryover_amount DECIMAL(12,0) NOT NULL DEFAULT 0 COMMENT '繰越金額',
    current_amount DECIMAL(12,0) NOT NULL DEFAULT 0 COMMENT '今回御買上額',
    tax_amount DECIMAL(12,0) NOT NULL DEFAULT 0 COMMENT '消費税',
    total_amount DECIMAL(12,0) NOT NULL DEFAULT 0 COMMENT '御買上合計',
    invoice_amount DECIMAL(12,0) NOT NULL DEFAULT 0 COMMENT '今回御請求額',
    payment_term VARCHAR(25),
    remarks VARCHAR(80),
    status TINYINT NOT NULL DEFAULT 0 COMMENT '0=未請求, 1=請求締済, 2=入金紐付済, 3=締解除済',
    created_by INT UNSIGNED,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id),
    UNIQUE KEY uk_invoice_no (tenant_id, fiscal_year_id, invoice_no),
    INDEX idx_invoice_date (tenant_id, fiscal_year_id, invoice_date),
    INDEX idx_customer (tenant_id, fiscal_year_id, customer_code),
    INDEX idx_status (tenant_id, fiscal_year_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 請求書紐付（売上伝票-請求書）
-- =====================================================
CREATE TABLE invoice_sales_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    sales_slip_id INT UNSIGNED NOT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    FOREIGN KEY (sales_slip_id) REFERENCES sales_slips(id),
    UNIQUE KEY uk_invoice_sales (invoice_id, sales_slip_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 入金伝票ヘッダ
-- =====================================================
CREATE TABLE payment_slips (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    fiscal_year_id INT UNSIGNED NOT NULL,
    payment_slip_no VARCHAR(10) NOT NULL,
    payment_date DATE NOT NULL,
    staff_code VARCHAR(8),
    customer_code VARCHAR(14) NOT NULL,
    total_amount DECIMAL(12,0) NOT NULL DEFAULT 0,
    status TINYINT NOT NULL DEFAULT 0 COMMENT '0=未締, 1=請求締済',
    remarks VARCHAR(40),
    print_detail_on_invoice TINYINT NOT NULL DEFAULT 0,
    receipt_remarks VARCHAR(40),
    created_by INT UNSIGNED,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id),
    UNIQUE KEY uk_payment_slip_no (tenant_id, fiscal_year_id, payment_slip_no),
    INDEX idx_payment_date (tenant_id, fiscal_year_id, payment_date),
    INDEX idx_customer (tenant_id, fiscal_year_id, customer_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 入金明細
-- =====================================================
CREATE TABLE payment_details (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_slip_id INT UNSIGNED NOT NULL,
    line_no SMALLINT UNSIGNED NOT NULL,
    payment_type_code VARCHAR(4) NOT NULL,
    amount DECIMAL(9,0) NOT NULL DEFAULT 0,
    remarks VARCHAR(40),
    FOREIGN KEY (payment_slip_id) REFERENCES payment_slips(id) ON DELETE CASCADE,
    UNIQUE KEY uk_payment_detail_line (payment_slip_id, line_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 入金-請求書紐付
-- =====================================================
CREATE TABLE payment_invoice_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_slip_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED NOT NULL,
    FOREIGN KEY (payment_slip_id) REFERENCES payment_slips(id),
    FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    UNIQUE KEY uk_payment_invoice (payment_slip_id, invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 帳票出力状態
-- =====================================================
CREATE TABLE output_statuses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slip_type VARCHAR(20) NOT NULL COMMENT 'sales, invoice, payment, receipt',
    slip_id INT UNSIGNED NOT NULL,
    output_type VARCHAR(20) NOT NULL COMMENT 'slip, delivery, receipt, invoice, receipt_slip',
    output_at DATETIME NOT NULL,
    output_by INT UNSIGNED,
    FOREIGN KEY (output_by) REFERENCES users(id),
    UNIQUE KEY uk_output_status (slip_type, slip_id, output_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 年次繰越履歴
-- =====================================================
CREATE TABLE year_rollover_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    from_fiscal_year_id INT UNSIGNED NOT NULL,
    to_fiscal_year_id INT UNSIGNED NOT NULL,
    rollover_type TINYINT NOT NULL COMMENT '1=初回, 2=残高のみ, 3=残高+マスタ',
    executed_by INT UNSIGNED NOT NULL,
    executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (from_fiscal_year_id) REFERENCES fiscal_years(id),
    FOREIGN KEY (to_fiscal_year_id) REFERENCES fiscal_years(id),
    FOREIGN KEY (executed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- 税率マスタ（推定: 年度×日付で税率を保持）
-- =====================================================
CREATE TABLE tax_rates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    fiscal_year_id INT UNSIGNED NOT NULL,
    effective_date DATE NOT NULL,
    standard_rate DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    reduced_rate DECIMAL(5,2) NOT NULL DEFAULT 8.00,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
