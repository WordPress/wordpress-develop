/**
 * External dependencies
 */
import {
	chmodSync,
	existsSync,
	mkdirSync,
	readFileSync,
	rmSync,
	writeFileSync,
} from 'node:fs';
import { dirname, join } from 'node:path';

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'WordPress installation with SQLite', () => {
	const wpConfig = join( process.cwd(), 'wp-config.php' );
	const documentRoot = join( process.cwd(), process.env.LOCAL_DIR ?? 'src' );
	// The setup creates wp-config.php next to wp-config-sample.php, which the build copies to the document root.
	const newWpConfig = existsSync( join( documentRoot, 'wp-config-sample.php' ) )
		? join( documentRoot, 'wp-config.php' )
		: wpConfig;
	const wpConfigSample = join( dirname( newWpConfig ), 'wp-config-sample.php' );
	const sampleDbDir = join( dirname( newWpConfig ), 'e2e-sqlite-database' );
	let wpConfigOriginal;
	let wpConfigSampleOriginal;

	/**
	 * Gets the SQLite database file path from the generated wp-config.php.
	 *
	 * @return {string|null} The database file path, or null when it isn't set.
	 */
	function getDbPath() {
		const match =
			existsSync( newWpConfig ) &&
			readFileSync( newWpConfig, 'utf-8' ).match(
				/define\( 'DB_PATH', __DIR__ \. '([^']+)' \);/
			);

		return match ? join( dirname( newWpConfig ), match[ 1 ] ) : null;
	}

	/**
	 * Deletes the SQLite database files.
	 *
	 * @param {string} dbPath The database file path.
	 */
	function deleteDbFiles( dbPath ) {
		for ( const suffix of [ '', '-journal', '-shm', '-wal' ] ) {
			rmSync( dbPath + suffix, { force: true } );
		}
	}

	/**
	 * Opens the first step of the wp-config.php setup.
	 *
	 * @param {import('@playwright/test').Page} page The page.
	 */
	async function openSetup( page ) {
		// The server can briefly still see the deleted wp-config.php; retry until the setup starts.
		await expect( async () => {
			await page.goto( '/wp-admin/setup-config.php?step=1' );
			await expect( page.getByLabel( 'Database Type' ) ).toBeVisible();
		}, 'should open the setup' ).toPass( { timeout: 10_000 } );
	}

	/**
	 * Runs the installation from the last setup screen, which creates the SQLite database.
	 *
	 * @param {import('@playwright/test').Page} page The page.
	 */
	async function runInstallation( page ) {
		const installUrl = await page
			.getByRole( 'link', { name: 'Run the installation' } )
			.getAttribute( 'href' );

		// PHP can briefly serve the previous wp-config.php; retry until the new one is used.
		await expect( async () => {
			await page.goto( `/wp-admin/${ installUrl }` );
			await expect(
				page.getByRole( 'heading', { name: 'Welcome' } )
			).toBeVisible();
		}, 'should show the installation page' ).toPass( { timeout: 10_000 } );
	}

	test.beforeEach( async () => {
		// Without wp-config.php, WordPress starts by creating it.
		wpConfigOriginal = readFileSync( wpConfig, 'utf-8' );
		rmSync( wpConfig );
	} );

	test.afterEach( async ( { page } ) => {
		const dbPath = getDbPath();
		if ( dbPath ) {
			deleteDbFiles( dbPath );
		}

		rmSync( newWpConfig, { force: true } );
		writeFileSync( wpConfig, wpConfigOriginal );

		if ( wpConfigSampleOriginal ) {
			writeFileSync( wpConfigSample, wpConfigSampleOriginal );
			wpConfigSampleOriginal = undefined;
		}
		rmSync( sampleDbDir, { recursive: true, force: true } );

		// PHP can briefly serve the previous wp-config.php; wait until the site answers as installed.
		await expect( async () => {
			await page.goto( '/wp-login.php' );
			expect( page.url() ).toMatch( /\/wp-login\.php$/ );
			await expect(
				page.getByRole( 'button', { name: 'Log In', exact: true } )
			).toBeVisible();
		}, 'should restore the installed site after the installation test' ).toPass(
			{ timeout: 10_000 }
		);
	} );

	test( 'should create wp-config.php and install WordPress with SQLite', async ( {
		page,
	} ) => {
		await openSetup( page );

		// SQLite needs no connection details.
		await page.getByLabel( 'Database Type' ).selectOption( 'sqlite' );
		await expect( page.getByLabel( 'Database Name' ) ).toBeHidden();
		await expect( page.getByLabel( 'Database Host' ) ).toBeHidden();
		await expect( page.getByLabel( 'Table Prefix' ) ).toBeVisible();

		await page.getByRole( 'button', { name: 'Submit' } ).click();

		await expect(
			page.getByText( /WordPress can now communicate with your database/ )
		).toBeVisible();
		await expect(
			page.getByText(
				/Your SQLite database will be created in the wp-content\/database directory/
			)
		).toBeVisible();

		expect( readFileSync( newWpConfig, 'utf-8' ) ).toContain(
			"define( 'DB_ENGINE', 'sqlite' );"
		);
		const dbPath = getDbPath();
		expect( dirname( dbPath ) ).toBe(
			join( documentRoot, 'wp-content', 'database' )
		);
		expect( dbPath ).toMatch( /\/\.ht\.[A-Za-z0-9]{32}\.sqlite$/ );
		expect(
			existsSync( dbPath ),
			'should create the database only when the installation runs'
		).toBe( false );

		await runInstallation( page );
		expect( existsSync( dbPath ), 'should create the database' ).toBe( true );

		await page.getByLabel( 'Site Title' ).fill( 'WordPress on SQLite' );
		await page.getByLabel( 'Username' ).fill( 'admin' );
		await page.getByLabel( 'Password', { exact: true } ).fill( '' );
		await page.getByLabel( 'Password', { exact: true } ).fill( 'password' );
		await page.getByLabel( /Confirm use of weak password/ ).check();
		await page.getByLabel( 'Your Email' ).fill( 'test@example.com' );

		await page.getByRole( 'button', { name: 'Install WordPress' } ).click();

		await expect( page.getByRole( 'heading', { name: 'Success!' } ) ).toBeVisible();

		await page.getByRole( 'link', { name: 'Log In' } ).click();
		await page.getByLabel( 'Username or Email Address' ).fill( 'admin' );
		await page.getByLabel( 'Password', { exact: true } ).fill( 'password' );
		await page.getByRole( 'button', { name: 'Log In' } ).click();

		await expect(
			page.getByRole( 'heading', { name: 'Welcome to WordPress', level: 2 } )
		).toBeVisible();
	} );

	test( 'should create the database at a changed path', async ( { page } ) => {
		await openSetup( page );
		await page.getByLabel( 'Database Type' ).selectOption( 'sqlite' );
		await page.getByRole( 'button', { name: 'Submit' } ).click();

		await expect(
			page.getByText( /WordPress can now communicate with your database/ )
		).toBeVisible();

		// Before the installation runs, changing DB_PATH is enough to use another location.
		const generatedDbPath = getDbPath();
		mkdirSync( sampleDbDir );
		chmodSync( sampleDbDir, 0o777 );
		writeFileSync(
			newWpConfig,
			readFileSync( newWpConfig, 'utf-8' ).replace(
				/define\( 'DB_PATH', __DIR__ \. '[^']+' \);/,
				"define( 'DB_PATH', __DIR__ . '/e2e-sqlite-database/database.sqlite' );"
			)
		);

		await runInstallation( page );

		expect( existsSync( join( sampleDbDir, 'database.sqlite' ) ) ).toBe( true );
		expect( existsSync( generatedDbPath ) ).toBe( false );
	} );

	test( 'should not recreate a missing SQLite database', async ( { page } ) => {
		await openSetup( page );
		await page.getByLabel( 'Database Type' ).selectOption( 'sqlite' );
		await page.getByRole( 'button', { name: 'Submit' } ).click();
		await runInstallation( page );

		// An empty database would make the site look new, and anyone could install it.
		const dbPath = getDbPath();
		deleteDbFiles( dbPath );

		await expect( async () => {
			await page.goto( '/wp-admin/install.php' );
			await expect(
				page.getByRole( 'heading', {
					name: 'Error establishing a database connection',
				} )
			).toBeVisible();
		}, 'should fail to connect to the missing database' ).toPass( {
			timeout: 10_000,
		} );

		expect( existsSync( dbPath ), 'should not create the database' ).toBe(
			false
		);
	} );

	test( 'should use the database path from wp-config-sample.php', async ( {
		page,
	} ) => {
		// A host can set the database path in wp-config-sample.php, for example to a directory outside the web root.
		mkdirSync( sampleDbDir );
		chmodSync( sampleDbDir, 0o777 );

		const dbEngineDefine = "define( 'DB_ENGINE', 'mysql' );";
		const dbPathDefine =
			"define( 'DB_PATH', __DIR__ . '/e2e-sqlite-database/database.sqlite' );";
		wpConfigSampleOriginal = readFileSync( wpConfigSample, 'utf-8' );
		writeFileSync(
			wpConfigSample,
			wpConfigSampleOriginal.replace(
				"define( 'DB_HOST',",
				`${ dbEngineDefine }\r\n${ dbPathDefine }\r\ndefine( 'DB_HOST',`
			)
		);

		await openSetup( page );
		await page.getByLabel( 'Database Type' ).selectOption( 'sqlite' );
		await page.getByRole( 'button', { name: 'Submit' } ).click();

		await expect(
			page.getByText( /WordPress can now communicate with your database/ )
		).toBeVisible();

		// The host chose the location, so there is nothing to recommend.
		await expect(
			page.getByText( /Your SQLite database will be created in/ )
		).toHaveCount( 0 );

		// The selected engine replaces the one from the sample, and the sample's path is kept.
		const config = readFileSync( newWpConfig, 'utf-8' );
		expect( config.match( /define\( 'DB_ENGINE',/g ) ).toHaveLength( 1 );
		expect( config ).toContain( "define( 'DB_ENGINE', 'sqlite' );" );
		expect( config.match( /define\( 'DB_PATH',/g ) ).toHaveLength( 1 );
		expect( config ).toContain( dbPathDefine );

		await runInstallation( page );
		expect( existsSync( join( sampleDbDir, 'database.sqlite' ) ) ).toBe( true );
	} );
} );
