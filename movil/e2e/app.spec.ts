import { expect, test } from '@playwright/test';

test('sin sesión, la app muestra el login', async ({ page }) => {
  await page.goto('/');
  await expect(page.locator('h1')).toContainText('Iniciar sesión');
});
