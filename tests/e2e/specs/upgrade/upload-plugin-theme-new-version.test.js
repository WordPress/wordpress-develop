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
	activatePlugin,
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
		await activatePlugin('classic-editor');

		await visitAdminPage('plugin-install.php', uploadPluginUrl);
		const pluginPath = path.join(
			__dirname,
			'..',
			'..',
			'fixtures',
			'plugins',
			'classic-editor.1.6.2.zip'
		);
		const input = await page.$('#pluginzip');
		await input.uploadFile(pluginPath);
		await page.click('#install-plugin-submit');
		await page.waitForSelector('.button.button-primary');

		await activatePlugin('classic-editor');

		// Delete the plugin
		await deactivatePlugin('classic-editor');
		await uninstallPlugin('classic-editor');
	});
});
