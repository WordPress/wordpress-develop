const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const childProcess = require( 'node:child_process' );
const { mkdtemp, mkdir, readFile, rm, utimes, writeFile } = require( 'node:fs/promises' );
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

test( 'uses a separate local E2E port and classifies local URLs', ( t ) => {
	const { getBaseURL, isDefaultEnvironment } = loadDatabase();
	const originalPort = process.env.LOCAL_PORT;
	const originalE2EPort = process.env.LOCAL_E2E_PORT;
	const originalBaseURL = process.env.WP_BASE_URL;
	t.after( () => {
		if ( originalPort === undefined ) {
			delete process.env.LOCAL_PORT;
		} else {
			process.env.LOCAL_PORT = originalPort;
		}
		if ( originalE2EPort === undefined ) {
			delete process.env.LOCAL_E2E_PORT;
		} else {
			process.env.LOCAL_E2E_PORT = originalE2EPort;
		}
		if ( originalBaseURL === undefined ) {
			delete process.env.WP_BASE_URL;
		} else {
			process.env.WP_BASE_URL = originalBaseURL;
		}
	} );

	process.env.LOCAL_PORT = '8899';
	delete process.env.LOCAL_E2E_PORT;
	delete process.env.WP_BASE_URL;
	assert.equal( getBaseURL(), 'http://localhost:8900/' );
	assert.equal( isDefaultEnvironment( 'http://localhost:8900' ), true );
	assert.equal( isDefaultEnvironment( 'http://127.0.0.1:8900' ), true );
	assert.equal( isDefaultEnvironment( 'http://localhost:8899' ), false );
	assert.equal( isDefaultEnvironment( 'http://localhost:8889' ), false );
	assert.equal( isDefaultEnvironment( 'https://localhost:8900' ), false );
	process.env.WP_BASE_URL = 'http://localhost:8899';
	assert.equal( getBaseURL(), 'http://localhost:8900/' );
	assert.equal( isDefaultEnvironment( getBaseURL() ), true );
	process.env.LOCAL_E2E_PORT = '8899';
	assert.throws( () => getBaseURL(), /distinct valid TCP ports/ );
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
		LOCAL_E2E_PORT: process.env.LOCAL_E2E_PORT,
		WP_BASE_URL: process.env.WP_BASE_URL,
	};
	const testRoot = await mkdtemp( path.join( os.tmpdir(), 'wp-e2e-database-' ) );
	await mkdir( path.join( testRoot, 'tests/e2e' ), { recursive: true } );
	await writeFile(
		path.join( testRoot, 'wp-config.php' ),
		"<?php\ndefine( 'DB_NAME', 'wordpress_develop' );\n$table_prefix = 'wp_';\n"
	);
	process.chdir( testRoot );
	process.env.LOCAL_DIR = 'build';
	process.env.LOCAL_PORT = '8899';
	process.env.LOCAL_E2E_PORT = '8900';
	delete process.env.WP_BASE_URL;

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
		if ( args.includes( 'get' ) && args.includes( 'table_prefix' ) ) {
			return { status: 0, stdout: 'wp_', stderr: '' };
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
		await database.setupE2EDatabase( 'http://localhost:8900' ),
		true
	);
	const rootConfig = await readFile( path.join( testRoot, 'wp-config.php' ), 'utf8' );
	assert.match( rootConfig, /BEGIN WP Core E2E config routing/ );
	assert.match( rootConfig, /DB_NAME', 'wordpress_develop'/ );
	assert.match(
		await readFile( path.join( testRoot, 'wp-config-e2e.php' ), 'utf8' ),
		/\$table_prefix = 'wp_';/
	);
	const firstRunCommandCount = commands.length;
	await assert.rejects(
		database.setupE2EDatabase( 'http://localhost:8900' ),
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
			args.includes( 'eval' ) &&
			args.includes( '--url=http://localhost:8899' ) &&
			args.some(
				( arg ) =>
					arg.includes(
						'DROP DATABASE IF EXISTS wordpress_develop_e2e'
					) &&
					arg.includes(
						'CREATE DATABASE wordpress_develop_e2e'
					)
			)
		)
	);
	assert.equal(
		commands.some( ( args ) => args.includes( 'db' ) ),
		false
	);
	assert.ok(
		commands.some( ( args ) =>
			args.includes( 'WP_CACHE_KEY_SALT' ) &&
			args.includes( 'set' ) &&
			args.some( ( arg ) =>
				arg.includes( 'WP_CONFIG_PATH=/var/www/wp-config-e2e.' )
			)
		)
	);
	assert.ok(
		commands.some( ( args ) =>
			args.includes( 'DB_NAME' ) &&
			args.includes( 'wordpress_develop_e2e' ) &&
			args.some( ( arg ) =>
				arg.includes( 'WP_CONFIG_PATH=/var/www/wp-config-e2e.' )
			)
		)
	);
	assert.ok(
		commands.some( ( args ) =>
			args.includes( 'memcached_servers' ) &&
			args.some( ( arg ) => arg.includes( 'memcached-e2e' ) )
		)
	);
	assert.ok(
		commands.some( ( args ) =>
			args.includes( 'install' ) &&
			args.includes( '--url=http://localhost:8900' ) &&
			args.includes( 'WP_CONFIG_PATH=/var/www/wp-config-e2e.php' )
		)
	);

	await database.restoreE2EDatabase();
	assert.doesNotMatch(
		await readFile( path.join( testRoot, 'wp-config.php' ), 'utf8' ),
		/BEGIN WP Core E2E config routing/
	);
	await assert.rejects(
		readFile( path.join( testRoot, 'wp-config-e2e.php' ), 'utf8' ),
		{ code: 'ENOENT' }
	);
} );

test( 'recovers a stale setup marker and regenerates a clean E2E table prefix', async ( t ) => {
	const originalCwd = process.cwd();
	const originalEnvironment = {
		LOCAL_DIR: process.env.LOCAL_DIR,
		LOCAL_PORT: process.env.LOCAL_PORT,
		LOCAL_E2E_PORT: process.env.LOCAL_E2E_PORT,
		WP_BASE_URL: process.env.WP_BASE_URL,
	};
	const testRoot = await mkdtemp( path.join( os.tmpdir(), 'wp-e2e-recovery-' ) );
	const e2eDirectory = path.join( testRoot, 'tests/e2e' );
	const interruptedLockId = '11111111-1111-4111-8111-111111111111';
	await mkdir( e2eDirectory, { recursive: true } );
	await writeFile(
		path.join( testRoot, 'wp-config.php' ),
		"<?php\ndefine( 'DB_NAME', 'wordpress_develop' );\n$table_prefix = 'wp_';\n"
	);
	await writeFile(
		path.join( testRoot, 'wp-config-e2e.php' ),
		"<?php\ndefine( 'DB_NAME', 'wordpress_develop_e2e' );\n$table_prefix = 'wp_e2e_';\n"
	);
	await writeFile(
		path.join( e2eDirectory, '.database-state.json' ),
		JSON.stringify( {
			lockId: interruptedLockId,
			localDir: 'build',
			configurationMode: 'separate',
			originalDbName: 'wordpress_develop',
			originalTablePrefix: 'wp_',
			originalUploads: null,
			originalCacheKeySalt: null,
		} )
	);
	await writeFile(
		path.join( testRoot, `wp-config-e2e.${ interruptedLockId }.tmp.php` ),
		'<?php // interrupted temporary config'
	);
	await writeFile(
		path.join( e2eDirectory, '.database-lock' ),
		JSON.stringify( {
			pid: 999999999,
			id: interruptedLockId,
		} )
	);
	const recoveryLockPath = path.join( e2eDirectory, '.database-lock.recovery' );
	await writeFile( recoveryLockPath, '' );
	const oldTime = new Date( Date.now() - 60_000 );
	await utimes( recoveryLockPath, oldTime, oldTime );

	process.chdir( testRoot );
	process.env.LOCAL_DIR = 'build';
	process.env.LOCAL_PORT = '8899';
	process.env.LOCAL_E2E_PORT = '8900';
	delete process.env.WP_BASE_URL;
	t.mock.method( process, 'kill', ( pid ) => {
		if ( pid !== process.pid ) {
			const error = new Error( 'Process not found.' );
			error.code = 'ESRCH';
			throw error;
		}
	} );
	t.mock.method( childProcess, 'spawnSync', ( _command, args ) => {
		if ( args.includes( 'get' ) && args.includes( 'DB_NAME' ) ) {
			return { status: 0, stdout: 'wordpress_develop', stderr: '' };
		}
		if ( args.includes( 'get' ) && args.includes( 'table_prefix' ) ) {
			return { status: 0, stdout: 'wp_', stderr: '' };
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
		await database.setupE2EDatabase( 'http://localhost:8900' ),
		true
	);
	assert.match(
		await readFile( path.join( testRoot, 'wp-config-e2e.php' ), 'utf8' ),
		/\$table_prefix = 'wp_';/
	);
	assert.doesNotMatch(
		await readFile( path.join( testRoot, 'wp-config-e2e.php' ), 'utf8' ),
		/wp_e2e_/
	);
	await assert.rejects(
		readFile( path.join( testRoot, `wp-config-e2e.${ interruptedLockId }.tmp.php` ) ),
		{ code: 'ENOENT' }
	);
	await database.restoreE2EDatabase();
} );
