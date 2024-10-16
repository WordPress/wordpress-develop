import {
	visitAdminPage,
	isCurrentURL
} from '@wordpress/e2e-test-utils';

const oldPluginFilePath = './tests/e2e/test-data/akismet.4.2.zip';
const newPluginFilePath = './tests/e2e/test-data/akismet.4.2.1.zip';
const pluginSlug = 'akismet';

async function installPluginFromUpload( filePath ){

	await visitAdminPage('plugin-install.php');

	await page.waitForSelector('.upload');
	await page.click('.upload');

	const file = await page.waitForSelector('#pluginzip');
	await file.uploadFile( filePath );

	// Check Install now button status
	const isDisabled = await page.$eval('#install-plugin-submit', (button) => {
		return button.disabled;
	  });

	// Click on Install Now button when it's not disabled
	if(isDisabled == false ){
	await page.waitForSelector('#install-plugin-submit')
	await page.click('#install-plugin-submit')
	}

}


async function checkPluginVersion( slug ){

	await visitAdminPage('plugins.php');

	const pluginVersion = await page.waitForSelector(
		'tr[data-slug="${ slug }"] td.column-description div.plugin-version-author-uri'
	);

	return pluginVersion;
	  

}


describe( 'Manage Plugin', () => {

	// beforeAll( async () => {
		
	// } );
	
	it( 'Upload old version of plugin', async () => {

		const pluginVersion = '4.2';
		let primaryButton;

		await installPluginFromUpload( oldPluginFilePath );

		// Verify plugin is installed
		primaryButton = await page.waitForSelector(
			'.button.button-primary'
		);
		
		expect(
			await primaryButton.evaluate( ( element ) => element.innerText )
		).toContain( 'Activate Plugin' );

		// Verify if right version is installed
		const pluginVersionText = await checkPluginVersion( pluginSlug );

		expect(
			await pluginVersionText.evaluate( ( element ) => element.innerText )
		).toContain( pluginVersion );

	} );

	it( 'Upload new version of plugin', async () => {

		const pluginVersion = '4.2.1';
		let primaryButton;

		await installPluginFromUpload( newPluginFilePath );

		// Verify it shows message to replace current plugin 
		primaryButton = await page.waitForSelector(
			'.button.button-primary'
		);
		
		expect(
			await primaryButton.evaluate( ( element ) => element.innerText )
		).toContain( 'Replace current with uploaded' );

		// Install the new plugin
		await page.click('.button.button-primary');


		// Verify new plugin is installed
		primaryButton = await page.waitForSelector(
			'.button.button-primary'
		);
		
		expect(
			await primaryButton.evaluate( ( element ) => element.innerText )
		).toContain( 'Activate Plugin' );

		// Verify if right version is installed
		const pluginVersionText = await checkPluginVersion( pluginSlug );

		expect(
			await pluginVersionText.evaluate( ( element ) => element.innerText )
		).toContain( pluginVersion );

	} );

	it( 'cancel the old version upload', async () => {

		await installPluginFromUpload( oldPluginFilePath );

		const cancelButton = await page.waitForSelector(
			'p.update-from-upload-actions a:nth-child(2)'
		);

		await page.click('p.update-from-upload-actions a:nth-child(2)');

		//verify if current URL is plugin-install.php
		await isCurrentURL('plugin-install.php');

	} );
});