const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const childProcess = require( 'node:child_process' );
const { mkdtemp, mkdir, rm } = require( 'node:fs/promises' );
const os = require( 'node:os' );
const path = require( 'node:path' );
const databasePath = require.resolve( './database.js' );

/**
 * Load the database module without reusing its cached environment configuration.
 *
 * @return {Object} Database configuration API.
 */
function loadDatabase() {
	delete require.cache[ databasePath ];
	return require( databasePath );
}

test( 'uses the local environment port when classifying a URL', ( t ) => {
	const { getBaseURL, isDefaultEnvironment } = loadDatabase();
	const originalPort = process.env.LOCAL_PORT;
	const originalBaseURL = process.env.WP_BASE_URL;
	t.after( () => {
		if ( originalPort === undefined ) {
			delete process.env.LOCAL_PORT;
		} else {
			process.env.LOCAL_PORT = originalPort;
		}
		if ( originalBaseURL === undefined ) {
			delete process.env.WP_BASE_URL;
		} else {
			process.env.WP_BASE_URL = originalBaseURL;
		}
	} );

	process.env.LOCAL_PORT = '8899';
	delete process.env.WP_BASE_URL;
	assert.equal( getBaseURL(), 'http://localhost:8899/' );
	assert.equal( isDefaultEnvironment( 'http://localhost:8899' ), true );
	assert.equal( isDefaultEnvironment( 'http://127.0.0.1:8899' ), true );
	assert.equal( isDefaultEnvironment( 'http://localhost:8889' ), false );
	assert.equal( isDefaultEnvironment( 'https://localhost:8899' ), false );
} );

test( 'custom test sites require explicit isolation confirmation', async ( t ) => {
	const { setupE2EDatabase } = loadDatabase();
	const originalIsolation = process.env.WP_E2E_ISOLATED;
	t.after( () => {
		if ( originalIsolation === undefined ) {
			delete process.env.WP_E2E_ISOLATED;
		} else {
			process.env.WP_E2E_ISOLATED = originalIsolation;
		}
	} );

	delete process.env.WP_E2E_ISOLATED;
	await assert.rejects(
		setupE2EDatabase( 'https://wordpress.example.test' ),
		/WP_BASE_URL is customized/i
	);

	process.env.WP_E2E_ISOLATED = 'true';
	assert.equal(
		await setupE2EDatabase( 'https://wordpress.example.test' ),
		false
	);
} );

test( 'isolates database setup and rejects another run in the same checkout', async ( t ) => {
	const originalCwd = process.cwd();
	const originalEnvironment = {
		LOCAL_DIR: process.env.LOCAL_DIR,
		LOCAL_PORT: process.env.LOCAL_PORT,
	};
	const testRoot = await mkdtemp( path.join( os.tmpdir(), 'wp-e2e-database-' ) );
	await mkdir( path.join( testRoot, 'tests/e2e' ), { recursive: true } );
	process.chdir( testRoot );
	process.env.LOCAL_DIR = 'build';
	process.env.LOCAL_PORT = '8899';

	const commands = [];
	t.mock.method( childProcess, 'spawnSync', ( _command, args ) => {
		commands.push( args );
		if ( args.includes( 'get' ) && args.includes( 'DB_NAME' ) ) {
			return { status: 0, stdout: 'wordpress_develop', stderr: '' };
		}
		if (
			args.includes( 'get' ) &&
			( args.includes( 'UPLOADS' ) ||
				args.includes( 'WP_CACHE_KEY_SALT' ) )
		) {
			return {
				status: 1,
				stdout: '',
				stderr: 'The constant is not defined.',
			};
		}
		return { status: 0, stdout: '', stderr: '' };
	} );

	const database = loadDatabase();
	t.after( async () => {
		process.chdir( originalCwd );
		for ( const [ key, value ] of Object.entries( originalEnvironment ) ) {
			if ( value === undefined ) {
				delete process.env[ key ];
			} else {
				process.env[ key ] = value;
			}
		}
		const resolvedTestRoot = path.resolve( testRoot );
		assert.ok( resolvedTestRoot.startsWith( `${ path.resolve( os.tmpdir() ) }${ path.sep }` ) );
		await rm( resolvedTestRoot, { recursive: true, force: true } );
	} );

	assert.equal(
		await database.setupE2EDatabase( 'http://localhost:8899' ),
		true
	);
	const firstRunCommandCount = commands.length;
	await assert.rejects(
		database.setupE2EDatabase( 'http://localhost:8899' ),
		/Another E2E run is using this checkout/
	);
	assert.equal( commands.length, firstRunCommandCount );
	assert.ok(
		commands.every( ( args ) =>
			args.includes( '--path=/var/www/build' )
		)
	);
	assert.ok(
		commands.some( ( args ) =>
			args.some( ( arg ) =>
				arg.includes( 'CREATE DATABASE IF NOT EXISTS wordpress_develop_e2e' )
			)
		)
	);
	assert.ok(
		commands.some( ( args ) =>
			args.includes( 'WP_CACHE_KEY_SALT' ) && args.includes( 'set' )
		)
	);

	await database.restoreE2EDatabase();
} );
