const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('General Settings', () => {
    let defaultTimezone;

    test.beforeEach(async ({ admin, page }) => {
        await admin.visitAdminPage('options-general.php');

        // Capture the default timezone
        defaultTimezone = await page.locator('select#timezone_string').inputValue();
    });

    test.afterEach(async ({ page }) => {
         // Revert to default timezone 
         await page.locator('select#timezone_string').selectOption(defaultTimezone);
         await page.click('#submit');
    });
    test('changes timezone', async ({ admin, page }) => {
      
        // Change timezone
        await page.locator('select#timezone_string').selectOption('Africa/Libreville');
        await page.click('#submit');

        // Verify success message
        await expect(page.locator('#setting-error-settings_updated')).toHaveText(
            'Settings saved.Dismiss this notice.'
        );

        // Verify selected timezone persists
        const selectedTimezone = await page.locator('select#timezone_string').inputValue();
        await expect(selectedTimezone).toBe('Africa/Libreville');       
       
    });
});
