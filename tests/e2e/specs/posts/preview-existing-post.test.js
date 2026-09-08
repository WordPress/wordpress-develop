/**
 * WordPress dependencies
 */
const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Preview Existing Post', () => {
  test.beforeEach(async ({ admin, editor }, testInfo) => {
    const title = 'preview post';
    testInfo.data = { title };
    await admin.createNewPost({ title });
    await editor.publishPost();
  });

  test.afterEach(async ({ requestUtils }, testInfo) => {
    const { title } = testInfo.data;
    await requestUtils.deleteAllPosts();
  });

/**
 * Selects a menu item radio by its name and verifies `aria-checked=true`.
 *
 * @param {Page} page - The Playwright page instance.
 * @param {string} menuItem - The name of the menu item to select (e.g., 'Tablet', 'Mobile', 'Desktop').
 */
  async function selectMenuItemRadio(page, menuItem) {
    const menuItemRadio = page.getByRole('menuitemradio', { name: menuItem });

    // Click the menu item radio
    await menuItemRadio.click();

    // Assert that `aria-checked=true` after the click
    await expect(menuItemRadio).toHaveAttribute('aria-checked', 'true');
  }

  test('Can Preview Existing Post', async ({ page, admin }, testInfo) => {
    const { title } = testInfo.data;

    // Navigate to Posts list
    await admin.visitAdminPage('/');
    await page.getByRole('link', { name: 'Posts', exact: true }).click();
   
    //Click on the Test Post.
    await page.locator(`text="${title}"`).first().click();

    // Click Preview button 
    await page.getByRole('button', { name: 'View', exact: true }).click();

    // Test for 'Tablet'
    await selectMenuItemRadio(page, 'Tablet');

    // Test for 'Mobile'
    await selectMenuItemRadio(page, 'Mobile');

    // Test for 'Desktop'
    await selectMenuItemRadio(page, 'Desktop');

    // Open Preview in a new tab
    const [popup] = await Promise.all([
      page.waitForEvent('popup'),
      page.locator('text="Preview in new tab"').click(),
    ]);
    await popup.waitForLoadState();
    await expect(popup).toHaveURL(/preview=true/);
    await expect(popup.locator('body')).toContainText(title);
  });
});
