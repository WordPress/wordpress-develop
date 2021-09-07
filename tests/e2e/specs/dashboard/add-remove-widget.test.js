import {
    visitAdminPage,
} from '@wordpress/e2e-test-utils';


describe('Show/hide and reorder dashboard widgets', () => {
    it('Allows to hide a dashboard widget using screen options', async() => {
        await visitAdminPage('/');

        const screenOptionsButton = await page.waitForSelector('#show-settings-link');
        await screenOptionsButton.click();

        // Hide the welcome panel and check that it is hidden
        await page.click('#wp_welcome_panel-hide');

        const hiddenWelcomPanel = await page.$eval('#welcome-panel', (elem) => {
            return window.getComputedStyle(elem).getPropertyValue('display') === 'none';
        });
        expect(hiddenWelcomPanel).toBe(true);

        // Show back the welcome panel
        await page.click('#wp_welcome_panel-hide');
    });

    it('Allows to hide the Welcome panel widget using the dismiss button', async() => {
        await visitAdminPage('/');

        const dismissButton = await page.waitForSelector('a.welcome-panel-close');
        await dismissButton.click();

        const hiddenWelcomPanel = await page.$eval('#welcome-panel', (elem) => {
            return window.getComputedStyle(elem).getPropertyValue('display') === 'none';
        });

        expect(hiddenWelcomPanel).toBe(true);

        // Show back the welcome panel
        const screenOptionsButton = await page.waitForSelector('#show-settings-link');
        await screenOptionsButton.click();
        await page.click('#wp_welcome_panel-hide');
    });
});