# 販売管理システム (Sales Management System)

Web型販売管理システム。一般的なレンタルサーバー（Xserver、ConoHa WING等）で動作するPHP+MySQLベースのアプリケーションです。

## 概要

得意先・商品などのマスタを基盤に、売上伝票、請求書、入金、売掛残高を一元管理し、販売実績・粗利益・前年対比などの帳票を出力します。販売・入金実績は会計CSVとして外部会計システムへ連携できます。

## 技術スタック

| 項目 | 技術 |
|------|------|
| バックエンド | PHP 8.1+ |
| データベース | MySQL 8.0+ |
| フロントエンド | HTML/CSS/JavaScript |
| PDF生成 | TCPDF ( planned ) |
| URLリライト | Apache .htaccess |

## 機能一覧

### 基本情報・マスタ管理
| 機能 | 概要 |
|------|------|
| 基本情報登録 | 会社情報、番号付番方法、端数処理、帳票設定 |
| 自社部門マスタ | 部門のCRUD、CSV入出力対応 |
| 自社担当者マスタ | 担当者のCRUD、CSV出力対応 |
| 入金区分マスタ | 現金/振込/手数料/手形の区分管理 |
| 摘要マスタ | 売上/入金/請求/領収書の摘要管理 |
| 得意先マスタ | 得意先のCRUD、税処理6種類、請求方法設定 |
| 商品カテゴリーマスタ | 大/中/小分類の階層構造管理 |
| 商品マスタ | 商品のCRUD、単価1-4、原価、軽減税率対応 |

### 売上管理
| 機能 | 概要 |
|------|------|
| 売上伝票入力 | ヘッダ+明細(20行)、自動計算、同時出力機能 |
| 売上伝票訂正・削除 | 未請求伝票のみ修正可能 |
| 売上伝票複写 | 既存伝票を新番号で複製 |
| 赤伝登録 | 数量・金額を負数にした新規伝票作成 |
| 売上伝票出力 | 売上伝票/納品書/受領書のPDF出力対応 |

### 請求管理
| 機能 | 概要 |
|------|------|
| 請求書作成 | 締め請求対象の抽出と請求書PDF生成 |
| 請求書再出力 | 作成済み請求書の再印刷 |
| 請求締解除 | 指定請求書以降の後続請求を同時解除 |

### 入金管理
| 機能 | 概要 |
|------|------|
| 入金入力 | 複数明細対応、請求書紐付機能 |
| 入金訂正・削除 | 未締伝票のみ修正可能 |
| 入金実績一覧 | 得意先・期間条件でCSV/PDF出力 |
| 領収書出力 | 請求書紐付済み入金の領収書生成 |

### 帳票・分析
| 帳票名 | 内容 |
|--------|------|
| 得意先元帳 | 得意先別の売掛取引明細 |
| 売掛残高一覧 | 得意先別の売掛残高一覧 |
| 売上明細表 | 得意先/商品/担当者/日/部門別 |
| 売上日報 | 日次集計レポート |
| 売上月報 | 月次集計レポート |
| 売上推移表 | 月別実績と昨年対比 |
| 売上順位表 | 順位・構成比・累計 |
| 売上伸び率順位表 | 前年差・伸び率 |
| 売上分析表 | 純売上・数量・粗利益・粗利率 |

### 照会
| 機能 | 概要 |
|------|------|
| 得意先マスタ照会 | 得意先情報の参照専用表示 |
| 商品マスタ照会 | 商品情報の参照専用表示 |

### 外部連携
| 機能 | 概要 |
|------|------|
| 会計データ出力 | 経理システム向けCSV出力 |

### 運用管理
| 機能 | 概要 |
|------|------|
| 年次繰越 | 新年度生成、期首残高・マスタ繰越 |
| ログインユーザ情報変更 | 本人の名称・メール・パスワード変更 |
| ログインユーザ管理 | 利用者のCRUD、パスワード初期化 |
| 権限管理 | カテゴリー・機能単位の権限付与 |

## ディレクトリ構成

```
SalesManagementSystem/
├── public/                    # Web公開ディレクトリ
│   ├── index.php             # エントリーポイント
│   ├── .htaccess             # URLリライト
│   ├── css/
│   │   └── style.css         # スタイルシート
│   ├── js/                   # JavaScript
│   ├── images/               # 画像
│   └── uploads/              # アップロード先
├── app/
│   ├── Config/               # 設定ファイル
│   │   ├── app.php           # アプリ設定
│   │   └── database.php      # DB接続設定
│   ├── Controllers/          # コントローラー
│   │   ├── Auth/             # 認証関連
│   │   ├── Master/           # マスタ管理
│   │   ├── Sales/            # 売上管理
│   │   ├── Invoice/          # 請求管理
│   │   ├── Payment/          # 入金管理
│   │   ├── Report/           # 帳票・分析
│   │   ├── Inquiry/          # 照会
│   │   ├── External/         # 外部連携
│   │   └── Admin/            # 運用管理
│   ├── Models/               # モデル
│   │   ├── BaseModel.php     # 基底モデル
│   │   ├── Customer.php      # 得意先
│   │   ├── Product.php       # 商品
│   │   ├── SalesSlip.php     # 売上伝票
│   │   ├── Invoice.php       # 請求書
│   │   ├── Payment.php       # 入金伝票
│   │   └── ...
│   ├── Helpers/              # ヘルパー
│   │   ├── Database.php      # PDO接続
│   │   ├── Auth.php          # 認証
│   │   ├── Session.php       # セッション管理
│   │   ├── Validator.php     # バリデーション
│   │   ├── Numbering.php     # 番号付番
│   │   ├── TaxCalculator.php # 税計算
│   │   └── Csrf.php          # CSRF対策
│   └── Config/               # 設定
├── views/                    # ビューファイル
├── sql/
│   ├── schema.sql            # テーブル定義
│   └── seed.sql              # 初期データ
└── README.md
```

## セットアップ手順

### 1. ダウンロード

```bash
git clone https://github.com/YOUR_USERNAME/SalesManagementSystem.git
```

### 2. データベース作成

```sql
CREATE DATABASE sales_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. テーブル作成

```bash
mysql -u ユーザー名 -p sales_management < sql/schema.sql
```

### 4. 初期データ投入

```bash
mysql -u ユーザー名 -p sales_management < sql/seed.sql
```

### 5. 環境変数の設定

`.env.example` をコピーして実値を設定:

```bash
cp .env.example .env
```

`.env` に接続情報・初期パスワードを記述します（`.env` は gitignore 済みでコミットされません）:

```dotenv
# データベース接続
DB_HOST=localhost
DB_NAME=sales_management
DB_USER=your_user
DB_PASS=your_password
DB_CHARSET=utf8mb4

# FTP デプロイ接続（deploy.sh が参照）
FTP_HOST=ftp.example.com
FTP_USER=your_ftp_user
FTP_PASS=your_ftp_password

# 初期パスワード（setup.php による初期化、パスワード初期化機能で使用）
INITIAL_ADMIN_PASSWORD=change_me
DEFAULT_USER_PASSWORD=change_me
SUPER_ADMIN_PASSWORD=change_me
```

### 6. 初期データ投入（ユーザ作成含む）

`sql/seed.sql` はマスタ・テナントデータのみで、**ユーザは作成しません**。
管理者・スーパー管理者の作成は `public/setup.php` をブラウザで実行します（`.env` の `INITIAL_ADMIN_PASSWORD` / `SUPER_ADMIN_PASSWORD` で初期パスワードが設定されます）。

> **注意**: `setup.php` は本番サーバーで誰でも実行できてしまうため、**初期化後は必ずサーバーから削除**してください。

### 7. ディレクトリ権限

```bash
chmod 755 public/
chmod 777 public/uploads/
```

## デフォルトログイン情報

初期管理者（`setup.php` 実行後）:

| 項目 | 値 |
|------|-----|
| 契約者ID | DEMO001 |
| ログインID | admin |
| パスワード | `.env` の `INITIAL_ADMIN_PASSWORD` |

スーパー管理者（`/SalesManagementSystem/system/admin/login.php`）:

| 項目 | 値 |
|------|-----|
| ログインID | superadmin |
| パスワード | `.env` の `SUPER_ADMIN_PASSWORD` |

> 導入後は必ず `.env` の各パスワードを変更してください。

## 主要業務ルール

| ID | ルール |
|----|--------|
| BR-001 | 本年度決算月と自社締日は初回登録後変更不可 |
| BR-002 | 得意先の請求方法は登録後変更不可 |
| BR-003 | 未請求売上がある得意先は税処理を変更不可 |
| BR-005 | 売上日付は選択年度内のみ登録可 |
| BR-007 | 請求締済みの売上・入金伝票は訂正・削除不可 |
| BR-009 | 赤伝は元伝票を削除せず、負数の新規伝票を作成 |
| BR-010 | 締解除は指定請求書以降の後続請求も同時解除 |
| BR-012 | 請求書紐付は入金額と同額の請求書1枚のみ |
| BR-015 | 年次繰越は取消不可 |

## セキュリティ対策

- パスワード: `password_hash()` / `password_verify()` によるハッシュ化
- CSRF対策: トークン検証
- XSS対策: `htmlspecialchars()` によるエスケープ
- SQLインジェクション対策: PDOプリペアドステートメント
- セキュリティヘッダー: X-Content-Type-Options, X-Frame-Options, X-XSS-Protection

## 非機能要件

| 項目 | 要件 |
|------|------|
| 対応ブラウザ | Chrome, Edge, Firefox, Safari |
| 画面解像度 | 1024×768以上、1920×1080推奨 |
| 無料版制限 | 売上伝票行数1,000、ユーザID最大3 |
| 有料版 | 売上伝票行数無制限、基本ID数4 |

## ライセンス

MIT License
