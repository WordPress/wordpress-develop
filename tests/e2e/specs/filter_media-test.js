import {
    loginUser,
    visitAdminPage,
    pressKeyTimes
} from '@wordpress/e2e-test-utils';

describe('media', () => {
    try {
        it('filter media by type', async() => {
            await loginUser();
            await visitAdminPage('upload.php');
            await page.waitForSelector('#attachment-filter', { timeout: 0 }, { visible: true });
            await page.click('#attachment-filter');
            await pressKeyTimes(page, 'ArrowDown', 2);
            await pressKeyTimes(page, 'Enter', 1);
            await page.click('#post-query-submit');
        });
    } catch (error) {
        console.log(error);
    }
});
