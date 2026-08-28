/**
 * External dependencies
 */
import { writeFileSync, readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
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

		// The test completes a full install under the `wp_e2e_` prefix. Drop those
		// tables, otherwise the next run finds a pre-existing install and never
		// reaches the installation screen it's meant to be testing.
		const tables = [
			'commentmeta', 'comments', 'links', 'options', 'postmeta', 'posts',
			'term_relationships', 'term_taxonomy', 'termmeta', 'terms', 'usermeta', 'users',
		];
		const dropTablesPhp = tables
			.map( ( table ) => `global $wpdb; $wpdb->query( "DROP TABLE IF EXISTS wp_e2e_${ table }" );` )
			.join( ' ' );

		execFileSync(
			process.execPath,
			[
				join( process.cwd(), 'tools/local-env/scripts/docker.js' ),
				'exec',
				'--user',
				'wp_php',
				'cli',
				'wp',
				'eval',
				dropTablesPhp,
			],
			{ stdio: 'inherit' }
		);
	} );

	test( 'should install WordPress with pre-existing database credentials', async ( { page } ) => {
		// The config file was just rewritten on the host; retry the navigation
		// (not just the URL check) since the container's view of the file can
		// lag behind the write by a request or two.
		await expect( async () => {
			await page.goto( '/' );
			expect( page.url() ).toMatch( /wp-admin\/install\.php$/ );
		}, 'should redirect to the installation page' ).toPass( { timeout: 10_000 } );

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
		await page.getByLabel( 'Your Email' ).fill( 'test@example.com' );

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
