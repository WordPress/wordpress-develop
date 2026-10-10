/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe('Activate Deactivate Plugin', () => {
    const pluginSlug = 'hello-dolly';
    const pluginRowSelector = `tr[data-slug="${pluginSlug}"]`;

    test.beforeEach(async ({ admin }) => {
        await admin.visitAdminPage('plugins.php');
    });

    test('activates the plugin', async ({ admin, page }) => {
        const activateLink = page.locator(`${pluginRowSelector} .activate a`);
        const deactivateLink = page.locator(`${pluginRowSelector} .deactivate a`);

        // Check if the plugin is already activated
        if (await deactivateLink.isVisible()) {
            console.log(`${pluginSlug} is already activated.`);
            return;
        }

        // Activate the plugin
        await activateLink.click();

        // Verify the plugin is activated
        await expect(deactivateLink).toBeVisible();
        console.log(`${pluginSlug} has been activated successfully.`);
    });

    test('deactivates the plugin', async ({ admin, page }) => {
        const activateLink = page.locator(`${pluginRowSelector} .activate a`);
        const deactivateLink = page.locator(`${pluginRowSelector} .deactivate a`);

        // Check if the plugin is already deactivated
        if (await activateLink.isVisible()) {
            console.log(`${pluginSlug} is already deactivated.`);
            return;
        }

        // Deactivate the plugin
        await deactivateLink.click();

        // Verify the plugin is deactivated
        await expect(activateLink).toBeVisible();
        console.log(`${pluginSlug} has been deactivated successfully.`);
    });
});
