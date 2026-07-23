const { chromium } = require('playwright');

const BASE_URL = 'https://debugprint.com/SalesManagementSystem';

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await context.newPage();

  console.log('=== データベース初期化開始 ===');

  try {
    // setup.phpにアクセスして初期化を実行
    console.log('1. setup.phpにアクセス中...');
    await page.goto(`${BASE_URL}/setup.php`, { timeout: 60000, waitUntil: 'networkidle' });

    // ページの内容を取得
    const content = await page.textContent('body');
    console.log('ページ内容:', content.substring(0, 300));

    // 成功メッセージを確認
    const hasSuccess = await page.locator('.success').count();
    const hasError = await page.locator('.error').count();

    if (hasSuccess > 0) {
      console.log('✓ データベース初期化成功');
    } else if (hasError > 0) {
      const errorText = await page.locator('.error').textContent();
      console.log('✗ エラー:', errorText);
    } else {
      console.log('⚠ 結果を判定できませんでした');
    }

    // スクリーンショット保存
    await page.screenshot({ path: '/home/vps/workspace/SalesManagementSystem/tests/setup-result.png' });
    console.log('スクリーンショット保存');

  } catch (e) {
    console.error('エラー:', e.message);
  }

  // ログイン画面の確認
  console.log('\n=== ログイン画面確認 ===');
  try {
    await page.goto(`${BASE_URL}/`, { timeout: 30000, waitUntil: 'networkidle' });
    console.log('✓ ログイン画面表示');

    const title = await page.title();
    console.log('タイトル:', title);

    // ログインフォームの確認
    const loginForm = await page.locator('form').count();
    console.log('フォーム数:', loginForm);

    await page.screenshot({ path: '/home/vps/workspace/SalesManagementSystem/tests/login-page.png' });

  } catch (e) {
    console.error('エラー:', e.message);
  }

  await browser.close();
  console.log('\n=== 完了 ===');
})();
