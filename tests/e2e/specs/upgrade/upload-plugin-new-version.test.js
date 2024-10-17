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
	deactivatePlugin,
	__experimentalRest as rest,
} from "@wordpress/e2e-test-utils";
import { addQueryArgs } from "@wordpress/url";

async function getPlugins() {
	const response = await rest({
		method: 'GET',
		path: "/wp/v2/plugins",
	});
	return response;
}

async function checkPluginStatus(pluginName) {
	const plugins = await getPlugins();
	const plugin = plugins.find(plugin => plugin.name === pluginName);

	return plugin ? plugin.status : 'uninstalled';
}

async function checkAndInstallPlugin(pluginSlug, pluginName) {
	const pluginStatus = await checkPluginStatus(pluginName);

	if (pluginStatus === 'active') {
		await deactivatePlugin(pluginSlug);
		await uninstallPlugin(pluginSlug);
		await installPlugin(pluginSlug, pluginName);
	} else if (pluginStatus === 'inactive') {
		await uninstallPlugin(pluginSlug);
		await installPlugin(pluginSlug, pluginName);
	} else {
		await installPlugin(pluginSlug, pluginName);
	}
}

async function uploadNewPluginVersion(pluginPath) {
	const uploadPluginUrl = addQueryArgs('', { tab: 'upload' });
	await visitAdminPage('plugin-install.php', uploadPluginUrl);
	const input = await page.$('#pluginzip');
	await input.uploadFile(pluginPath);
	await page.click('#install-plugin-submit');
}

describe('Manage uploading new plugin/theme version', () => {
	const pluginSlug = 'classic-editor';
	const pluginName = 'Classic Editor';
	const newUploadedPluginVersion = '1.6';
	const classicEdirorPath = path.join(
		__dirname,
		'..',
		'..',
		'fixtures',
		'plugins',
		`${pluginSlug}.${newUploadedPluginVersion}.zip`
	);

	afterEach(async () => {
		await uninstallPlugin(pluginSlug);
	});

	it('should replace a plugin when uploading a new version', async () => {
		await checkAndInstallPlugin(pluginSlug, pluginName);
		await uploadNewPluginVersion(classicEdirorPath);

		const upgradeHeading = await page.waitForSelector('.update-from-upload-heading');
		expect(
			await upgradeHeading.evaluate((element) => element.textContent)
		).toContain('This plugin is already installed.');

		await page.click('a.update-from-upload-overwrite');

		await page.waitForSelector('.wrap');
		const updatingParagraphs = await page.$$('.wrap p');
		let mergedMessages = await Promise.all(
			updatingParagraphs.map((message) => message.evaluate((element) => element.textContent))
		);
		mergedMessages = mergedMessages.join(' ');

		expect(mergedMessages).toContain('Downgrading the plugin');
		expect(mergedMessages).toContain('Removing the current plugin');
		expect(mergedMessages).toContain('Plugin downgraded successfully');

		await visitAdminPage('plugins.php');

		const classicEditorVersionRow = await page.waitForSelector(`tr[data-slug="${pluginSlug}"] .plugin-version-author-uri`);
		expect(
			await classicEditorVersionRow.evaluate((element) => element.textContent)
		).toContain('1.6');
	});

	it('should leave the previous version on a click on the "Cancel and go back" button', async () => {
		await checkAndInstallPlugin(pluginSlug, pluginName);
		await uploadNewPluginVersion(classicEdirorPath);
		await page.waitForSelector('.update-from-upload-heading');

		await page.click('.update-from-upload-actions a:not(.update-from-upload-overwrite)');

		// Check that the plugin is still installed
		const pluginStatus = await checkPluginStatus(pluginName);
		expect(pluginStatus).toBe('inactive');

		const plugins = await getPlugins();
		const currentClassicEditorVersion = plugins.find(plugin => plugin.name === pluginName).version;
		expect(currentClassicEditorVersion).not.toBe(newUploadedPluginVersion);
	});
});
