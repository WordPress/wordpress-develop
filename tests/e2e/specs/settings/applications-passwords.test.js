import {
    visitAdminPage,
    __experimentalRest as rest,
} from '@wordpress/e2e-test-utils';

async function getResponseForApplicationPassword() {
    return await rest({
        method: 'GET',
        path: '/wp/v2/users/me/application-passwords'
    });
}

async function createApplicationPassword(applicationName) {
    await visitAdminPage('profile.php');
    await page.waitForSelector('#new_application_password_name');
    await page.type('#new_application_password_name', applicationName);
    await page.click('#do_new_application_password');

    await page.waitForSelector('#application-passwords-section .notice');
}

async function revokeAllApplicationPasswords() {
    await visitAdminPage('profile.php');

    const revokeAllButtonVisibility = await page.evaluate(() => {
        const e = document.querySelector('.application-passwords-list-table-wrapper');
        if (!e) {
            return false;
        }
        const visibility = window.getComputedStyle(e);
        return visibility && visibility.display !== 'none' && visibility.visibility !== 'hidden' && visibility.opacity !== '0';
    });

    if (revokeAllButtonVisibility) {
        await page.click('#revoke-all-application-passwords');
        await page.keyboard.press('Enter');
        await page.waitForSelector('#application-passwords-section .notice-success');
    }
}

async function revokeAllApplicationPasswordsWithApi() {
    const response = await getResponseForApplicationPassword();
    const uuids = response.map(applicationPassword => applicationPassword.uuid);
    for (const uuid of uuids) {
        await rest({
            method: 'DELETE',
            path: `/wp/v2/users/me/application-passwords/${uuid}`
        });
    }
}

describe('Manage applications passwords', () => {
    const testApplicationName = 'Test Application';

    beforeEach(async() => {
        await revokeAllApplicationPasswordsWithApi();
    });

    it('correctly creates a new application password', async() => {
        await createApplicationPassword(testApplicationName);

        const response = await getResponseForApplicationPassword();
        expect(response[0]['name']).toBe(testApplicationName);

        const successMessage = await page.waitForSelector('#application-passwords-section .notice-success');
        expect(
            await successMessage.evaluate((element) => element.innerText)
        ).toContain(`Your new password for ${testApplicationName} is: \n\nBe sure to save this in a safe location. You will not be able to retrieve it.`);
    });

    it('should not allows to create two applications passwords with the same name', async() => {
        await createApplicationPassword(testApplicationName);
        await createApplicationPassword(testApplicationName);

        const errorMessage = await page.waitForSelector('#application-passwords-section .notice-error');

        expect(
            await errorMessage.evaluate((element) => element.textContent)
        ).toContain('Each application name should be unique.');
    });

    it('should correctly revokes a single application password', async() => {
        await createApplicationPassword(testApplicationName);

        const revokeApplicationButton = await page.waitForSelector('.application-passwords-user tr button.delete');
        await revokeApplicationButton.click();
        await page.keyboard.press('Enter');

        const successMessage = await page.waitForSelector('#application-passwords-section .notice-success');
        expect(
            await successMessage.evaluate((element) => element.textContent)
        ).toContain('Application password revoked.');

        const response = await getResponseForApplicationPassword();
        expect(response).toEqual([]);
    });

    it('correctly revokes all the application passwords', async() => {
        await createApplicationPassword(testApplicationName);
        await revokeAllApplicationPasswords();

        const successMessage = await page.waitForSelector('#application-passwords-section .notice-success');
        expect(
            await successMessage.evaluate((element) => element.textContent)
        ).toContain('All application passwords revoked.');

        const response = await getResponseForApplicationPassword();
        expect(response).toEqual([]);
    });
});
