/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const oldPluginFilePath = './tests/e2e/test-data/akismet.5.3.4.zip';
const newPluginFilePath = './tests/e2e/test-data/akismet.5.3.5.zip';
const pluginSlug = 'akismet';
const pluginTitle ='Akismet Anti-spam: Spam Protection';

/**
 * Uploads a plugin from a zip file.
 */
async function installPluginFromUpload( page, filePath, admin ) {
	await admin.visitAdminPage( 'plugin-install.php' );
	await page.click( '.upload' );
	const fileInput = await page.locator( '#pluginzip' );
	await fileInput.setInputFiles( filePath );

	const installButton = page.locator( '#install-plugin-submit' );
	const isDisabled = await installButton.isDisabled();
	if ( ! isDisabled ) {
		await installButton.click();
		await page.waitForLoadState( 'networkidle' );
	}
}

/**
 * Checks the version of an installed plugin.
 */
async function checkPluginVersion( page, pluginSlug, admin ) {
	await admin.visitAdminPage( 'plugins.php' ); // Update with your WordPress site URL
	const pluginVersionElement = page.locator(
		`tr[data-slug="${ pluginSlug }"] .inactive.second.plugin-version-author-uri`
	);
	const pluginVersionText = await pluginVersionElement.evaluate( ( el ) => {
		const text = el.textContent;
		// Extract the version number using a regex
		const versionMatch = text.match( /Version\s([\d.]+)/ );
		return versionMatch ? versionMatch[ 1 ] : null;
	} );
	return pluginVersionText;
}

test.describe( 'Manage Plugins: Upload and Remove', () => {
	
    test( 'uploads and installs an older version of the plugin', async ( {
		page,
		admin,
	} ) => {
		const expectedVersion = '5.3';

		await installPluginFromUpload( page, oldPluginFilePath, admin );
		const activateButton = page.locator( '.button.button-primary' );
		await expect( activateButton ).toHaveText( 'Activate Plugin' );

		const actualVersion = await checkPluginVersion(
			page,
			pluginSlug,
			admin
		);
		expect( actualVersion ).toContain( expectedVersion );

		const updateNotice = page.locator(
			`tr.plugin-update-tr[data-slug="${ pluginSlug }"] .update-message`
		);
		// Assert that the update notice is visible
		await expect( updateNotice ).toBeVisible();

		// Assert that the update notice contains the expected text
		const expectedText =
			`There is a new version of ${ pluginTitle } available.`;
		await expect( updateNotice ).toHaveText(
			new RegExp( expectedText, 'i' )
		);

		console.log(
			'Update notice is visible and contains the expected text.'
		);
	} );

	test( 'uploads and updates to a newer version of the plugin', async ( {
		page,
		admin,
	} ) => {
		const expectedVersion = '5.3.5';

		await installPluginFromUpload( page, newPluginFilePath, admin );
		const replaceButton = page.locator( '.button.button-primary' );
		await expect( replaceButton ).toHaveText(
			'Replace current with uploaded'
		);
		await replaceButton.click();

		const activateButton = page.locator( '.button.button-primary' );
		await expect( activateButton ).toHaveText( 'Activate Plugin' );

		const actualVersion = await checkPluginVersion(
			page,
			pluginSlug,
			admin
		);
		expect( actualVersion ).toContain( expectedVersion );
	} );

	test( 'removes the installed plugin successfully', async ( {
		page,
		admin,
	} ) => {
		await admin.visitAdminPage( 'plugins.php' );
		const pluginRowSelector = `tr[data-slug="${ pluginSlug }"]`;
		const deactivateLink = page.locator(
			`${ pluginRowSelector } .deactivate a`
		);
		if ( await deactivateLink.isVisible() ) {
			await deactivateLink.click();
			await page.waitForLoadState( 'networkidle' );
		}
        page.on( 'dialog', async ( dialog ) => {
            console.log('Dialog message:', dialog.message());
			await dialog.accept();
		} );

		const deleteLink = page.locator( `${ pluginRowSelector } .delete a` );
		await deleteLink.click();
        
		const deletionMessage = page.locator( 'tr.plugin-deleted-tr' );
		
		// Verify the content of the deletion message
		await expect( deletionMessage ).toContainText(
			'was successfully deleted'
		);
	} );
} );
