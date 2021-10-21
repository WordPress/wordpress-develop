/**
 * External dependencies
 */
import path from 'path';

/**
 * WordPress dependencies
 */
import {
	visitAdminPage,
	installPlugin,
	uninstallPlugin,
	activateTheme,
	deactivatePlugin,
	deactivateTheme,
} from "@wordpress/e2e-test-utils";
import { addQueryArgs } from "@wordpress/url";

describe('Manage uploading new plugin/theme version', () => {
	const uploadPluginUrl = addQueryArgs('', { tab: 'upload' });

	it('should replace a plugin when uploading a new version', async () => {
		await installPlugin('classic-editor', 'Classic Editor');

		await visitAdminPage('plugin-install.php', uploadPluginUrl);
		const pluginPath = path.join(
			__dirname,
			'..',
			'..',
			'fixtures',
			'plugins',
			'classic-editor.1.6.zip'
		);
		const input = await page.$('#pluginzip');
		await input.uploadFile(pluginPath);
		await page.click('#install-plugin-submit');

		const upgradeHeading = await page.waitForSelector('.update-from-upload-heading');
		expect(
			await upgradeHeading.evaluate((element) => element.textContent)
		).toContain('This plugin is already installed.');

		await page.click('a.update-from-upload-overwrite');

		await page.waitForSelector('.wrap');
		const updatingMessages = await page.$$('.wrap p');
		const mergedMessages = await Promise.all(
			updatingMessages.map((message) => message.evaluate((element) => element.textContent))
		);
		const mergedMessage = mergedMessages.join(' ');

		// Delete the plugin
		await deactivatePlugin('classic-editor');
		await uninstallPlugin('classic-editor');
	});

	it.todo('cancel and go back feature');
});
