/**
 * External dependencies
 */
import { writeFileSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

let wpConfigOriginal;

test.describe( 'WordPress installation process', () => {
	const wpConfig = join(
		process.cwd(),
		'wp-config.php',
	);


	test.beforeEach( async () => {
		wpConfigOriginal = readFileSync( wpConfig, 'utf-8' );
		// Changing the table prefix tricks WP into new install mode.
		writeFileSync(
			wpConfig,
			wpConfigOriginal.replace( `$table_prefix = 'wp_';`, `$table_prefix = 'wp_e2e_';` )
		);
	} );

	test.afterEach( async () => {
		writeFileSync( wpConfig, wpConfigOriginal );
	} );

	test( 'should install WordPress with pre-existing database credentials', async ( { page } ) => {
		await page.goto( '/' );

		await expect(
			page,
			'should redirect to the installation page'
		).toHaveURL( /wp-admin\/install\.php$/ );

		await expect(
			page.getByText( /WordPress database error/ ),
			'should not have any database errors'
		).not.toBeVisible();

		// First page: language selector. Keep default English (US).
		await page.getByRole( 'button', { name: 'Continue' } ).click();

		// Second page: enter site name, username & password.

		await expect( page.getByRole( 'heading', { name: 'Welcome' } ) ).toBeVisible();

		// This information matches tools/local-env/scripts/install.js.

		await page.getByLabel( 'Site Title' ).fill( 'WordPress Develop' );
		await page.getByLabel( 'Username' ).fill( 'admin' );
		await page.getByLabel( 'Password', { exact: true } ).fill( '' );
		await page.getByLabel( 'Password', { exact: true } ).fill( 'password' );
		await page.getByLabel( /Confirm use of weak password/ ).check()
		await page.getByLabel( 'Your Email' ).fill( 'test@test.com' );

		await page.getByRole( 'button', { name: 'Install WordPress' } ).click();

		// Installation finished, can now log in.

		await expect( page.getByRole( 'heading', { name: 'Success!' } ) ).toBeVisible();

		await page.getByRole( 'link', { name: 'Log In' } ).click();

		await expect(
			page,
			'should redirect to the login page'
		).toHaveURL( /wp-login\.php$/ );

		await page.getByLabel( 'Username or Email Address' ).fill( 'admin' );
		await page.getByLabel( 'Password', { exact: true } ).fill( 'password' );

		await page.getByRole( 'button', { name: 'Log In' } ).click();

		await expect(
			page.getByRole( 'heading', { name: 'Welcome to WordPress', level: 2 })
		).toBeVisible();
	} );
} );
