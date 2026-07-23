# 販売管理システム テスト仕様書

## テスト方針

| テスト種別 | 内容 | ツール |
|------------|------|--------|
| 疎通テスト | 全ページのHTTP応答確認 | Playwright |
| 単体テスト | 各CRUD操作の正常/異常系 | Playwright |
| 結合テスト | マスタ→伝票→請求→入金の一連フロー | Playwright |
| システムテスト | エンドツーエンドの業務フロー | Playwright |

## 疎通テスト（全20画面）

| ID | 画面 | URL | 期待結果 |
|----|------|-----|----------|
| CT-01 | ログイン | / | HTTP 200、ログインフォーム表示 |
| CT-02 | 基本情報登録 | /master/company | HTTP 200、フォーム表示 |
| CT-03 | 部門マスタ | /master/department | HTTP 200、一覧表示 |
| CT-04 | 担当者マスタ | /master/staff | HTTP 200、一覧表示 |
| CT-05 | 入金区分マスタ | /master/payment-type | HTTP 200、一覧表示 |
| CT-06 | 摘要マスタ | /master/description | HTTP 200、一覧表示 |
| CT-07 | 得意先マスタ | /master/customer | HTTP 200、一覧表示 |
| CT-08 | 商品カテゴリーマスタ | /master/category | HTTP 200、一覧表示 |
| CT-09 | 商品マスタ | /master/product | HTTP 200、一覧表示 |
| CT-10 | 売上伝票入力 | /sales/input | HTTP 200、入力フォーム表示 |
| CT-11 | 売上伝票検索 | /sales/search | HTTP 200、検索フォーム表示 |
| CT-12 | 売上伝票出力 | /sales/output | HTTP 200、一覧表示 |
| CT-13 | 請求書作成 | /invoice/create | HTTP 200、作成フォーム表示 |
| CT-14 | 入金入力 | /payment/input | HTTP 200、入力フォーム表示 |
| CT-15 | 得意先元帳 | /report/ledger | HTTP 200、検索フォーム表示 |
| CT-16 | 売掛残高一覧 | /report/balance | HTTP 200、一覧表示 |
| CT-17 | 売上明細表 | /report/sales-detail | HTTP 200、検索フォーム表示 |
| CT-18 | 会計データ出力 | /external/accounting | HTTP 200、出力フォーム表示 |
| CT-19 | ユーザ管理 | /admin/users | HTTP 200、一覧表示 |
| CT-20 | ユーザ情報変更 | /admin/user-info | HTTP 200、フォーム表示 |
| CT-21 | 年次繰越 | /admin/year | HTTP 200、画面表示 |

## 単体テスト（CRUD操作）

### マスタCRUD

| ID | 操作 | 画面 | テスト内容 | 期待結果 |
|----|------|------|------------|----------|
| UT-01 | 部門登録 | /master/department | 新規部門を登録 | 一覧に表示される |
| UT-02 | 部門削除 | /master/department | 部門を削除 | 一覧から消える |
| UT-03 | 担当者登録 | /master/staff | 新規担当者を登録 | 一覧に表示される |
| UT-04 | 担当者削除 | /master/staff | 担当者を削除 | 一覧から消える |
| UT-05 | 得意先登録 | /master/customer | 新規得意先を登録 | 一覧に表示される |
| UT-06 | 得意先削除 | /master/customer | 得意先を削除 | 一覧から消える |
| UT-07 | 商品登録 | /master/product | 新規商品を登録 | 一覧に表示される |
| UT-08 | 商品削除 | /master/product | 商品を削除 | 一覧から消える |

## 結合テスト（業務フロー）

| ID | フロー | テスト内容 | 期待結果 |
|----|--------|------------|----------|
| IT-01 | 売上伝票フロー | 得意先→商品→売上伝票登録 | 伝票番号が発番され、明細が保存される |
| IT-02 | 請求書フロー | 売上→請求書作成 | 請求書が作成され、売上が請求締済になる |
| IT-03 | 入金フロー | 請求書→入金登録 | 入金伝票が作成される |

## システムテスト

| ID | テスト内容 | 期待結果 |
|----|------------|----------|
| ST-01 | ログイン→ログアウト | 正常にセッションが切れる |
| ST-02 | 権限なしアクセス | 権限がないメニューが非表示 |
| ST-03 | CSV出力 | CSVファイルがダウンロードされる |
