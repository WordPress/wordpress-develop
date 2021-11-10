/**
 * External dependencies
 */
import path from 'path';

/**
 * WordPress dependencies
 */
import {
	visitAdminPage,
	installTheme,
	deleteTheme,
	isThemeInstalled,
	__experimentalRest as rest,
} from "@wordpress/e2e-test-utils";

const themeSlug = "hello-elementor";
const themeName = "Hello Elementor";
const themeVersion = '2.0.0';
const oldThemePath = path.join(
	__dirname,
	'..',
	'..',
	'test-data',
	'themes',
	`${themeSlug}.${themeVersion}.zip`
);

async function getInstalledThemes() {
	const themes = await rest({
		method: 'GET',
		path: "/wp/v2/themes",
	});
	return themes;
}

async function checkThemeVersion() {
	const themes = await getInstalledThemes();
	const theme = themes.find(theme => theme.stylesheet === themeSlug);
	return theme.version;
}

async function checkAndInstallTheme() {
	if (! await isThemeInstalled(themeSlug)) {
		await installTheme(themeSlug, themeName);
	} else {
		await deleteTheme(themeSlug);
		await installTheme(themeSlug, themeName);
	}
}

async function uploadOldTheme() {
	await visitAdminPage('theme-install.php');
	await page.waitForSelector('.upload-view-toggle');
	await page.click('.upload-view-toggle');
	const input = await page.$('#themezip');
	await input.uploadFile(oldThemePath);
	await page.click('#install-theme-submit');

}

describe('Manage uploading new theme versions', () => {

	it('should replace a theme when uploading a new version', async () => {
		await checkAndInstallTheme();
		await uploadOldTheme();

		const upgradeHeading = await page.waitForSelector('.update-from-upload-heading');
		expect(
			await upgradeHeading.evaluate((element) => element.textContent)
		).toContain('This theme is already installed.');

		await page.click('a.update-from-upload-overwrite');

		const messageWrapper = await page.waitForSelector('.wrap');
		const messages = await messageWrapper.evaluate(element => element.textContent);

		expect(messages).toContain('Removing the old version of the theme');
		expect(await checkThemeVersion(themeName)).toBe(themeVersion);
	});

	it('should cancel and go back to the current theme version', async () => {
		await checkAndInstallTheme();
		await uploadOldTheme();
		await page.waitForSelector('.update-from-upload-heading');

		await page.click('.update-from-upload-actions a:not(.update-from-upload-overwrite)');

		expect(await checkThemeVersion(themeName)).not.toBe(themeVersion);
	});
});
