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

// The prefix used to trick WP into "not installed" mode. Kept as a single
// constant since it has to stay in sync between the config rewrite and the
// cleanup query below.
const TEST_TABLE_PREFIX = 'wp_e2e_';

const TEST_TABLES = [
	'commentmeta', 'comments', 'links', 'options', 'postmeta', 'posts',
	'term_relationships', 'term_taxonomy', 'termmeta', 'terms', 'usermeta', 'users',
];

/**
 * Drops any tables left over under TEST_TABLE_PREFIX.
 *
 * Called both before the prefix swap (in case a previous run crashed and
 * left stale tables behind — otherwise this run fails once and only
 * "self-heals" on the next run) and after it (so the next run starts clean).
 *
 * `wp eval` exits 0 even when a `$wpdb->query()` call inside it returns
 * false, so each DROP's result is checked explicitly and reported via
 * `WP_CLI::error()`, which does set a non-zero exit code — otherwise a
 * failed cleanup would silently report as a passing test.
 */
function dropE2eTables() {
	const dropTablesPhp = TEST_TABLES
		.map( ( table ) => `
			$result = $wpdb->query( "DROP TABLE IF EXISTS ${ TEST_TABLE_PREFIX }${ table }" );
			if ( false === $result ) {
				WP_CLI::error( "Failed to drop ${ TEST_TABLE_PREFIX }${ table }: {$wpdb->last_error}" );
			}
		` )
		.join( '' );

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
			`global $wpdb; ${ dropTablesPhp }`,
		],
		{ stdio: 'inherit' }
	);
}

test.describe( 'WordPress installation process', () => {
	const wpConfig = join(
		process.cwd(),
		'wp-config.php',
	);


	test.beforeEach( async () => {
		// Read this before the cleanup call below, which can throw. Otherwise,
		// if it does, `afterEach` runs anyway (Playwright always runs it) and
		// crashes trying to restore `wp-config.php` from an unset variable,
		// masking the real cleanup error behind a confusing second one.
		wpConfigOriginal = readFileSync( wpConfig, 'utf-8' );

		dropE2eTables();

		// Changing the table prefix tricks WP into new install mode.
		const wpConfigPatched = wpConfigOriginal.replace(
			`$table_prefix = 'wp_';`,
			`$table_prefix = '${ TEST_TABLE_PREFIX }';`
		);

		// A prior run killed after this rewrite but before `afterEach` restores
		// it leaves wp-config.php stranded on TEST_TABLE_PREFIX. `.replace()`
		// then silently no-ops instead of throwing, and since the tables were
		// just cleared above, the site looks freshly uninstalled under the
		// already-stranded prefix — the test would pass while leaving the
		// checkout stuck. Fail loudly here instead.
		if ( wpConfigPatched === wpConfigOriginal ) {
			throw new Error(
				`wp-config.php does not contain the default table prefix. An interrupted run may have left it on '${ TEST_TABLE_PREFIX }'.`
			);
		}

		writeFileSync( wpConfig, wpConfigPatched );
	} );

	test.afterEach( async () => {
		writeFileSync( wpConfig, wpConfigOriginal );

		// The test completes a full install under TEST_TABLE_PREFIX. Drop
		// those tables, otherwise the next run finds a pre-existing install
		// and never reaches the installation screen it's meant to be testing.
		dropE2eTables();
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
