/**
 * WordPress dependencies
 */
const { test, expect } = require("@wordpress/e2e-test-utils-playwright");


test.describe("Bulk Delete Posts", () => {

  test.beforeEach(async ({ admin, editor }) => {
    // Create multiple posts for bulk deletion
    for (let i = 0; i < 2; i++) {
      const title = `Test Post ${i + 1}`;
      await admin.createNewPost({ title });
      await editor.publishPost();
    }
  });

  test("Can delete the posts in bulk", async ({ page, admin }) => {

    // Navigate to posts page
    await admin.visitAdminPage('/edit.php');

    // Assert the Posts page header
    expect(page.locator('.wp-heading-inline')).toHaveText('Posts');

    // Select all posts for bulk deletion
    // await page.locator('#cb-select-all-1').click();
    await page.locator('#cb-select-all-1').check();

    // Select "Trash" option from the bulk actions dropdown
    await page.selectOption('#bulk-action-selector-top', 'trash');
    // Apply the bulk action
    await page.locator('#doaction').click();

    // Assert that posts were moved to the trash
    await expect(page.locator("div[id='message'] p").first()).toHaveText(/moved to the Trash./);

  });
});