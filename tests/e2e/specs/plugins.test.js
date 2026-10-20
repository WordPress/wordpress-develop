/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const plugin = 'e2e-test-plugin/e2e-test-plugin';
const pluginName = 'E2E Test Plugin';

async function uploadPlugin( admin, page, version ) {
	await admin.visitAdminPage( 'plugin-install.php' );
	await page
		.getByRole( 'button', { name: 'Upload Plugin', exact: true } )
		.click();
	await page
		.locator( '#pluginzip' )
		.setInputFiles(
			`tests/e2e/assets/e2e-test-plugin-${ version }.zip`
		);
	await page
		.getByRole( 'button', { name: 'Install Now', exact: true } )
		.click();
}

test.describe( 'Plugin management', () => {
	let ownsPlugin;

	test.beforeEach( async ( { requestUtils } ) => {
		// The fixture slug requires an environment not shared with another test run.
		ownsPlugin = false;
		const plugins = await requestUtils.rest( { path: '/wp/v2/plugins' } );
		expect( plugins.map( ( item ) => item.plugin ) ).not.toContain(
			plugin
		);
		ownsPlugin = true;
	} );

	test.afterEach( async ( { requestUtils } ) => {
		if ( ! ownsPlugin ) {
			return;
		}
		const plugins = await requestUtils.rest( { path: '/wp/v2/plugins' } );
		const installed = plugins.find( ( item ) => item.plugin === plugin );
		if ( installed ) {
			if ( installed.status !== 'inactive' ) {
				await requestUtils.rest( {
					method: 'PUT',
					path: `/wp/v2/plugins/${ plugin }`,
					data: { status: 'inactive' },
				} );
			}
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/plugins/${ plugin }`,
			} );
		}
	} );

	test( 'uploads, activates and deactivates a plugin', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		await uploadPlugin( admin, page, '1.0' );
		await expect(
			page.getByText( 'Plugin installed successfully.', { exact: true } )
		).toBeVisible();
		await admin.visitAdminPage( 'plugins.php' );
		const row = page.locator( `tr[data-plugin="${ plugin }.php"]` );
		await expect( row ).toContainText( 'Version 1.0' );
		await row
			.getByRole( 'link', {
				name: `Activate ${ pluginName }`,
				exact: true,
			} )
			.click();
		await expect(
			row.getByRole( 'link', {
				name: `Deactivate ${ pluginName }`,
				exact: true,
			} )
		).toBeVisible();
		expect(
			(
				await requestUtils.rest( {
					path: `/wp/v2/plugins/${ plugin }`,
				} )
			).status
		).toBe( 'active' );
		await row
			.getByRole( 'link', {
				name: `Deactivate ${ pluginName }`,
				exact: true,
			} )
			.click();
		await expect(
			row.getByRole( 'link', {
				name: `Activate ${ pluginName }`,
				exact: true,
			} )
		).toBeVisible();
		expect(
			(
				await requestUtils.rest( {
					path: `/wp/v2/plugins/${ plugin }`,
				} )
			).status
		).toBe( 'inactive' );
	} );

	test( 'replaces an installed plugin with an uploaded version', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		await uploadPlugin( admin, page, '1.0' );
		await expect(
			page.getByText( 'Plugin installed successfully.', { exact: true } )
		).toBeVisible();
		expect(
			(
				await requestUtils.rest( {
					path: `/wp/v2/plugins/${ plugin }`,
				} )
			).version
		).toBe( '1.0' );
		await uploadPlugin( admin, page, '2.0' );
		await page
			.getByRole( 'link', {
				name: 'Replace current with uploaded',
				exact: true,
			} )
			.click();
		await expect(
			page.getByText( 'Plugin updated successfully.', { exact: true } )
		).toBeVisible();
		await admin.visitAdminPage( 'plugins.php' );
		await expect(
			page.locator( `tr[data-plugin="${ plugin }.php"]` )
		).toContainText( 'Version 2.0' );
		expect(
			(
				await requestUtils.rest( {
					path: `/wp/v2/plugins/${ plugin }`,
				} )
			).version
		).toBe( '2.0' );
	} );

	test( 'deletes an inactive plugin after confirmation', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		await uploadPlugin( admin, page, '1.0' );
		await expect(
			page.getByText( 'Plugin installed successfully.', { exact: true } )
		).toBeVisible();
		await admin.visitAdminPage( 'plugins.php' );
		const row = page.locator( `tr[data-plugin="${ plugin }.php"]` );
		await Promise.all( [
			page.waitForEvent( 'dialog' ).then( async ( dialog ) => {
				expect( dialog.type() ).toBe( 'confirm' );
				await dialog.accept();
			} ),
			row
				.getByRole( 'link', {
					name: `Delete ${ pluginName }`,
					exact: true,
				} )
				.click(),
		] );
		await expect( page.locator( '.plugin-deleted-tr' ) ).toContainText(
			`${ pluginName } was successfully deleted.`
		);
		await page.reload();
		await expect( row ).toHaveCount( 0 );
		const plugins = await requestUtils.rest( { path: '/wp/v2/plugins' } );
		expect( plugins.map( ( item ) => item.plugin ) ).not.toContain(
			plugin
		);
	} );
} );
