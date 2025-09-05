const { test, expect } = require('@playwright/test');

test('homepage layout', async ({ page }) => {
  await page.goto('http://127.0.0.1:8000/index.php');
  await expect(page).toHaveScreenshot('homepage.png');
});
