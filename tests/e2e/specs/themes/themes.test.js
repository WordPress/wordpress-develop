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
	isThemeInstalled,
	__experimentalRest as rest,
} from "@wordpress/e2e-test-utils";

const themeSlug = "twentysixteen";
const themeName = "Twenty Sixteen";
const oldThemePath = path.join(
	__dirname,
	'..',
	'..',
	'test-data',
	'themes',
	'twentysixteen.zip'
);

async function checkThemeVersion(themeName) {
	const themes = await rest({
		method: 'GET',
		path: "/wp/v2/themes",
	});
	const theme = themes.find(theme => theme.name === themeName);
	return theme.version;
}

async function uploadOldTheme() {
	if (! await isThemeInstalled(themeSlug)) {
		await installTheme(themeSlug, themeName);
	}

	await visitAdminPage('theme-install.php');
	await page.waitForSelector('.upload-view-toggle');
	await page.click('.upload-view-toggle');
	const input = await page.$('#themezip');
	await input.uploadFile(oldThemePath);
	await page.click('#install-theme-submit');
}

describe('Manage uploading new theme versions', () => {
	it('should replace a theme when uploading a new version', async () => {
		await installTheme(themeSlug, themeName);
		await uploadOldTheme();

		await page.waitForTimeout(50000);
	});
});
