/**
 * External dependencies
 */
const { randomUUID } = require( 'node:crypto' );
const { spawnSync } = require( 'node:child_process' );
const {
	copyFile,
	link,
	mkdir,
	readFile,
	rm,
	rename,
	stat,
	writeFile,
} = require( 'node:fs/promises' );
const path = require( 'node:path' );
const dotenv = require( 'dotenv' );
const dotenvExpand = require( 'dotenv-expand' );

dotenvExpand.expand(
	dotenv.config( { path: path.join( process.cwd(), '.env' ) } )
);

const E2E_DB_NAME = 'wordpress_develop_e2e';
const E2E_UPLOADS = 'wp-content/uploads-e2e';
const E2E_CONFIG_FILE = 'wp-config-e2e.php';
const ROOT_CONFIG_FILE = 'wp-config.php';
const E2E_MEMCACHED_HOST = 'memcached-e2e';
const E2E_DIRECTORY = path.join( process.cwd(), 'tests/e2e' );
const statePath = path.join( E2E_DIRECTORY, '.database-state.json' );
const lockPath = path.join( E2E_DIRECTORY, '.database-lock' );
const recoveryLockPath = `${ lockPath }.recovery`;
const rootConfigPath = path.join( process.cwd(), ROOT_CONFIG_FILE );
const e2eConfigPath = path.join( process.cwd(), E2E_CONFIG_FILE );
const E2E_CONFIG_ROUTER_START = '/* BEGIN WP Core E2E config routing. */';
const E2E_CONFIG_ROUTER_END = '/* END WP Core E2E config routing. */';

let lockId;

/**
 * Get the local environment's WordPress document root.
 *
 * @param {string} [localDir] Document root configured in the local environment.
 * @return {string} The document root directory.
 */
function getLocalDir( localDir = process.env.LOCAL_DIR || 'src' ) {
	if ( ! /^[A-Za-z0-9_-]+$/.test( localDir ) ) {
		throw new Error( 'LOCAL_DIR is not a safe WordPress document root.' );
	}

	return localDir;
}

/**
 * Get the test URL for the configured local environment or custom target.
 *
 * @return {string} Test environment URL.
 */
function getBaseURL() {
	const localPort = process.env.LOCAL_PORT || '8889';
	const e2ePort = getE2EPort();
	const baseURL = new URL(
		process.env.WP_BASE_URL || `http://localhost:${ e2ePort }`
	);
	const port =
		baseURL.port || ( baseURL.protocol === 'https:' ? '443' : '80' );

	if (
		[ 'localhost', '127.0.0.1' ].includes( baseURL.hostname ) &&
		port === localPort
	) {
		baseURL.port = e2ePort;
	}

	return baseURL.href;
}

/**
 * Get the local E2E site's port, one above the development site's port by default.
 *
 * @return {string} E2E site port.
 */
function getE2EPort() {
	const localPort = Number( process.env.LOCAL_PORT || '8889' );
	const e2ePort = Number( process.env.LOCAL_E2E_PORT || localPort + 1 );
	if (
		! Number.isInteger( localPort ) ||
		localPort < 1 ||
		localPort > 65535 ||
		! Number.isInteger( e2ePort ) ||
		e2ePort < 1 ||
		e2ePort > 65535 ||
		localPort === e2ePort
	) {
		throw new Error(
			'LOCAL_PORT and LOCAL_E2E_PORT must be distinct valid TCP ports.'
		);
	}
	return String( e2ePort );
}

/**
 * Run a WP-CLI command in the local development environment.
 *
 * @param {string[]} args       WP-CLI arguments.
 * @param {string}   localDir   WordPress document root.
 * @param {string}   configFile Path to the configuration file in `/var/www`.
 * @return {{ status: number, stdout: string, stderr: string }} WP-CLI command result.
 */
function runWpCli(
	args,
	localDir = getLocalDir(),
	configFile = ROOT_CONFIG_FILE
) {
	if ( ! /^[A-Za-z0-9_.-]+$/.test( configFile ) ) {
		throw new Error( 'The WordPress configuration path is not safe.' );
	}

	const result = spawnSync(
		process.execPath,
		[
			path.join( process.cwd(), 'tools/local-env/scripts/docker.js' ),
			'exec',
			'--env',
			`WP_CONFIG_PATH=/var/www/${ configFile }`,
			'--user',
			'wp_php',
			'cli',
			'wp',
			...args,
			`--path=/var/www/${ getLocalDir( localDir ) }`,
		],
		{
			cwd: process.cwd(),
			encoding: 'utf8',
		}
	);

	if ( result.error ) {
		throw result.error;
	}

	return {
		status: result.status ?? 1,
		stdout: result.stdout || '',
		stderr: result.stderr || '',
	};
}

/**
 * Run WP-CLI and throw if it fails.
 *
 * @param {string[]} args       WP-CLI arguments.
 * @param {string}   localDir   WordPress document root.
 * @param {string}   configFile Path to the configuration file in `/var/www`.
 * @return {string} Command output.
 */
function wpCli(
	args,
	localDir = getLocalDir(),
	configFile = ROOT_CONFIG_FILE
) {
	const result = runWpCli( args, localDir, configFile );

	if ( result.status !== 0 ) {
		throw new Error(
			result.stderr.trim() ||
				`WP-CLI command failed: ${ args.join( ' ' ) }`
		);
	}

	return result.stdout.trim();
}

/**
 * Get a wp-config.php constant, returning null when it is not defined.
 *
 * @param {string} name     Constant name.
 * @param {string} localDir WordPress document root.
 * @return {?string} Constant value, or null if undefined.
 */
function getConfigConstant( name, localDir ) {
	const result = runWpCli(
		[ 'config', 'get', name, '--type=constant' ],
		localDir
	);

	if ( result.status === 0 ) {
		return result.stdout.trim();
	}

	if ( /not defined/i.test( result.stderr ) ) {
		return null;
	}

	throw new Error(
		result.stderr.trim() || `Unable to read the ${ name } setting.`
	);
}

/**
 * Get a wp-config.php variable, returning null when it is not defined.
 *
 * @param {string} name     Variable name.
 * @param {string} localDir WordPress document root.
 * @return {?string} The configured variable value.
 */
function getConfigVariable( name, localDir ) {
	const result = runWpCli(
		[ 'config', 'get', name, '--type=variable' ],
		localDir
	);

	if ( result.status === 0 ) {
		return result.stdout.trim();
	}

	if ( /not defined/i.test( result.stderr ) ) {
		return null;
	}

	throw new Error(
		result.stderr.trim() || `Unable to read the ${ name } setting.`
	);
}

/**
 * Atomically replace a local configuration file.
 *
 * @param {string} filePath File to replace.
 * @param {string} contents New file contents.
 * @return {Promise<void>}
 */
async function replaceFileAtomically( filePath, contents ) {
	const temporaryPath = `${ filePath }.${ randomUUID() }.tmp`;
	try {
		const originalFile = await stat( filePath );
		await writeFile( temporaryPath, contents, {
			encoding: 'utf8',
			mode: originalFile.mode & 0o777,
		} );
		await rename( temporaryPath, filePath );
	} finally {
		await rm( temporaryPath, { force: true } );
	}
}

/**
 * Add the host-and-port router that keeps development and E2E requests on separate configs.
 *
 * @return {Promise<void>}
 */
async function addE2EConfigRouter() {
	const config = await readFile( rootConfigPath, 'utf8' );
	if (
		config.includes( E2E_CONFIG_ROUTER_START ) ||
		config.includes( E2E_CONFIG_ROUTER_END )
	) {
		throw new Error( 'The E2E configuration router is already present.' );
	}
	if ( ! config.startsWith( '<?php' ) ) {
		throw new Error( 'Cannot safely add routing to wp-config.php.' );
	}

	const router = `${ E2E_CONFIG_ROUTER_START }
$e2e_request = 'http://' . ( $_SERVER['HTTP_HOST'] ?? '' );
$e2e_host = strtolower( parse_url( $e2e_request, PHP_URL_HOST ) ?: '' );
$e2e_port = (string) ( parse_url( $e2e_request, PHP_URL_PORT ) ?: '80' );
if ( in_array( $e2e_host, array( 'localhost', '127.0.0.1' ), true ) && '${ getE2EPort() }' === $e2e_port ) {
	$e2e_config = __DIR__ . '/${ E2E_CONFIG_FILE }';
	if ( ! is_readable( $e2e_config ) ) {
		throw new RuntimeException( 'The E2E WordPress configuration is missing.' );
	}
	require $e2e_config;
	return;
}
${ E2E_CONFIG_ROUTER_END }
`;
	const routedConfig = config.replace( /^<\?php/, `<?php\n${ router }` );
	await replaceFileAtomically( rootConfigPath, routedConfig );
}

/**
 * Remove the host-and-port router from wp-config.php after the E2E run.
 *
 * @return {Promise<void>}
 */
async function removeE2EConfigRouter() {
	const config = await readFile( rootConfigPath, 'utf8' );
	const start = config.indexOf( E2E_CONFIG_ROUTER_START );
	const end = config.indexOf( E2E_CONFIG_ROUTER_END );
	if ( start === -1 && end === -1 ) {
		return;
	}
	if ( start === -1 || end < start ) {
		throw new Error( 'The E2E configuration router is incomplete.' );
	}

	let endAfterBlock = end + E2E_CONFIG_ROUTER_END.length;
	if ( config.slice( endAfterBlock, endAfterBlock + 2 ) === '\r\n' ) {
		endAfterBlock += 2;
	} else if ( config[ endAfterBlock ] === '\n' ) {
		endAfterBlock++;
	}

	await replaceFileAtomically(
		rootConfigPath,
		`${ config.slice( 0, start ) }${ config.slice( endAfterBlock ) }`
	);
}

/**
 * Create a standalone configuration for the E2E site.
 *
 * @param {string} localDir WordPress document root.
 * @return {Promise<void>}
 */
async function createE2EConfig( localDir ) {
	const temporaryConfigFile = `wp-config-e2e.${ lockId }.tmp.php`;
	const temporaryConfigPath = path.join( process.cwd(), temporaryConfigFile );
	await copyFile( rootConfigPath, temporaryConfigPath );

	try {
		wpCli(
			[ 'config', 'set', 'DB_NAME', E2E_DB_NAME, '--type=constant' ],
			localDir,
			temporaryConfigFile
		);
		wpCli(
			[ 'config', 'set', 'UPLOADS', E2E_UPLOADS, '--type=constant' ],
			localDir,
			temporaryConfigFile
		);
		wpCli(
			[
				'config',
				'set',
				'WP_CACHE_KEY_SALT',
				`e2e-${ randomUUID() }-`,
				'--type=constant',
			],
			localDir,
			temporaryConfigFile
		);
		wpCli(
			[
				'config',
				'set',
				'memcached_servers',
				`array( array( '${ E2E_MEMCACHED_HOST }', 11211 ) )`,
				'--raw',
				'--type=variable',
			],
			localDir,
			temporaryConfigFile
		);
		await rename( temporaryConfigPath, e2eConfigPath );
	} catch ( error ) {
		await rm( temporaryConfigPath, { force: true } );
		throw error;
	}
}

/**
 * Check whether the URL points to the E2E port in Core's local environment.
 *
 * @param {string} baseURL Test environment URL.
 * @return {boolean} Whether the URL points to the configured local environment.
 */
function isDefaultEnvironment( baseURL ) {
	const url = new URL( baseURL );
	const port =
		url.port || ( url.protocol === 'https:' ? '443' : '80' );
	const e2ePort = getE2EPort();

	return (
		[ 'localhost', '127.0.0.1' ].includes( url.hostname ) &&
		url.protocol === 'http:' &&
		port === e2ePort
	);
}

/**
 * Check whether a process is still running.
 *
 * @param {number} pid Process ID.
 * @return {boolean} Whether the process is running or its state cannot be verified.
 */
function isProcessRunning( pid ) {
	try {
		process.kill( pid, 0 );
		return true;
	} catch ( error ) {
		return error.code !== 'ESRCH';
	}
}

/**
 * Read the current run lock.
 *
 * @param {string} [lockFilePath] Lock file path.
 * @return {Promise<?{ pid: number, id: string }>} Current lock owner.
 */
async function readLock( lockFilePath = lockPath ) {
	try {
		const owner = JSON.parse( await readFile( lockFilePath, 'utf8' ) );
		if ( ! Number.isInteger( owner.pid ) || typeof owner.id !== 'string' ) {
			throw new Error( 'Invalid E2E database lock.' );
		}

		return owner;
	} catch ( error ) {
		if ( error.code === 'ENOENT' ) {
			return null;
		}

		throw new Error( `Cannot safely read ${ lockFilePath }. ${ error.message }` );
	}
}

/**
 * Create a lock file with complete owner data using an atomic hard link.
 *
 * @param {{ pid: number, id: string }} owner          Lock owner.
 * @param {string}                      [lockFilePath] Lock file path.
 * @return {Promise<void>}
 */
async function createLock( owner, lockFilePath = lockPath ) {
	const temporaryPath = `${ lockFilePath }.${ owner.id }.tmp`;
	await writeFile( temporaryPath, JSON.stringify( owner ), { flag: 'wx' } );
	try {
		await link( temporaryPath, lockFilePath );
	} finally {
		await rm( temporaryPath, { force: true } );
	}
}

/**
 * Remove a recovery lock left behind by a process that has exited.
 *
 * @return {Promise<boolean>} Whether the recovery lock is absent or was removed.
 */
async function removeStaleRecoveryLock() {
	let recoveryOwner;
	try {
		recoveryOwner = await readLock( recoveryLockPath );
	} catch ( error ) {
		// Older versions wrote an empty recovery marker. Only remove it once it is
		// old enough that its owner cannot still be in the recovery section.
		const markerContents = await readFile( recoveryLockPath, 'utf8' );
		if ( markerContents !== '' ) {
			throw error;
		}
		try {
			const marker = await stat( recoveryLockPath );
			if ( Date.now() - marker.mtimeMs < 5000 ) {
				return false;
			}
		} catch ( statError ) {
			if ( statError.code === 'ENOENT' ) {
				return true;
			}
			throw statError;
		}

		await rm( recoveryLockPath, { force: true } );
		return true;
	}

	if ( ! recoveryOwner ) {
		return true;
	}
	if ( isProcessRunning( recoveryOwner.pid ) ) {
		return false;
	}

	const currentOwner = await readLock( recoveryLockPath );
	if ( currentOwner?.id !== recoveryOwner.id ) {
		return false;
	}
	await rm( recoveryLockPath, { force: true } );
	return true;
}

/**
 * Acquire the checkout-wide lock before changing wp-config.php.
 *
 * @return {Promise<void>}
 */
async function acquireLock() {
	await mkdir( E2E_DIRECTORY, { recursive: true } );
	const owner = { pid: process.pid, id: randomUUID() };

	try {
		await createLock( owner );
		lockId = owner.id;
		return;
	} catch ( error ) {
		if ( error.code !== 'EEXIST' ) {
			throw error;
		}
	}

	const existingOwner = await readLock();
	if ( ! existingOwner ) {
		return acquireLock();
	}
	if ( isProcessRunning( existingOwner.pid ) ) {
		throw new Error(
			'Another E2E run is using this checkout. Wait for it to finish before starting another run.'
		);
	}

	const recoveryOwner = { pid: process.pid, id: randomUUID() };
	try {
		await createLock( recoveryOwner, recoveryLockPath );
	} catch ( error ) {
		if ( error.code === 'EEXIST' ) {
			if ( await removeStaleRecoveryLock() ) {
				return acquireLock();
			}
			throw new Error(
				'Another process is recovering an interrupted E2E run. Wait for it to finish before starting another run.'
			);
		}
		throw error;
	}

	try {
		const currentRecoveryOwner = await readLock( recoveryLockPath );
		if ( currentRecoveryOwner?.id !== recoveryOwner.id ) {
			throw new Error(
				'Another process took over E2E recovery. Wait for it to finish before starting another run.'
			);
		}

		const currentOwner = await readLock();
		if ( currentOwner && isProcessRunning( currentOwner.pid ) ) {
			throw new Error(
				'Another E2E run is using this checkout. Wait for it to finish before starting another run.'
			);
		}

		await rm( lockPath, { force: true } );
		try {
			await createLock( owner );
			lockId = owner.id;
		} catch ( error ) {
			if ( error.code !== 'EEXIST' ) {
				throw error;
			}
			return acquireLock();
		}
	} finally {
		const currentRecoveryOwner = await readLock( recoveryLockPath );
		if ( currentRecoveryOwner?.id === recoveryOwner.id ) {
			await rm( recoveryLockPath, { force: true } );
		}
	}
}

/**
 * Release the lock if this process still owns it.
 *
 * @return {Promise<void>}
 */
async function releaseLock() {
	if ( ! lockId ) {
		return;
	}

	const owner = await readLock();
	if ( owner?.id === lockId ) {
		await rm( lockPath, { force: true } );
	}
	lockId = undefined;
}

/**
 * Restore a saved configuration, including after a run was interrupted.
 *
 * @return {Promise<boolean>} Whether a saved configuration was restored.
 */
async function restoreSavedConfiguration() {
	let state;
	try {
		state = JSON.parse( await readFile( statePath, 'utf8' ) );
	} catch ( error ) {
		if ( error.code === 'ENOENT' ) {
			return false;
		}

		throw error;
	}

	if (
		typeof state.lockId !== 'string' ||
		! /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(
			state.lockId
		) ||
		typeof state.localDir !== 'string'
	) {
		throw new Error(
			'Invalid saved database configuration; refusing to restore it.'
		);
	}
	const legacyConfiguration = state.configurationMode !== 'separate';
	if (
		legacyConfiguration &&
		( typeof state.originalDbName !== 'string' ||
			! /^[A-Za-z0-9_-]+$/.test( state.originalDbName ) )
	) {
		throw new Error(
			'Invalid saved database name; refusing to restore it.'
		);
	}
	if (
		state.originalTablePrefix !== undefined &&
		( typeof state.originalTablePrefix !== 'string' ||
			! /^[A-Za-z0-9_]+$/.test( state.originalTablePrefix ) )
	) {
		throw new Error(
			'Invalid saved table prefix; refusing to restore it.'
		);
	}
	if (
		legacyConfiguration &&
		state.originalUploads !== null &&
		typeof state.originalUploads !== 'string'
	) {
		throw new Error(
			'Invalid saved uploads configuration; refusing to restore it.'
		);
	}
	if (
		legacyConfiguration &&
		state.originalCacheKeySalt !== null &&
		typeof state.originalCacheKeySalt !== 'string'
	) {
		throw new Error(
			'Invalid saved object-cache configuration; refusing to restore it.'
		);
	}

	const localDir = getLocalDir( state.localDir );

	if ( legacyConfiguration ) {
		wpCli(
			[ 'config', 'set', 'DB_NAME', state.originalDbName, '--type=constant' ],
			localDir
		);
		wpCli(
			[
				'config',
				'set',
				'table_prefix',
				state.originalTablePrefix || 'wp_',
				'--type=variable',
			],
			localDir
		);

		if ( state.originalUploads === null ) {
			wpCli( [ 'config', 'delete', 'UPLOADS', '--type=constant' ], localDir );
		} else {
			wpCli(
				[
					'config',
					'set',
					'UPLOADS',
					state.originalUploads,
					'--type=constant',
				],
				localDir
			);
		}

		if ( state.originalCacheKeySalt === null ) {
			wpCli(
				[ 'config', 'delete', 'WP_CACHE_KEY_SALT', '--type=constant' ],
				localDir
			);
		} else {
			wpCli(
				[
					'config',
					'set',
					'WP_CACHE_KEY_SALT',
					state.originalCacheKeySalt,
					'--type=constant',
				],
				localDir
			);
		}
	}

	await removeE2EConfigRouter();
	await rm( e2eConfigPath, { force: true } );
	await rm(
		path.join( process.cwd(), `wp-config-e2e.${ state.lockId }.tmp.php` ),
		{ force: true }
	);
	await rm( statePath, { force: true } );
	return true;
}

/**
 * Set up the E2E suite with its own database, uploads and cache namespace.
 *
 * @param {string} baseURL Test environment URL.
 * @return {Promise<boolean>} Whether the local environment was switched.
 */
async function setupE2EDatabase( baseURL ) {
	if ( ! isDefaultEnvironment( baseURL ) ) {
		if ( process.env.WP_E2E_ISOLATED !== 'true' ) {
			throw new Error(
				'Core E2E tests require an isolated test site when WP_BASE_URL is customized. Set WP_E2E_ISOLATED=true only when the site uses a disposable database and uploads directory.'
			);
		}

		return false;
	}

	await acquireLock();
	let configurationMayBeMutated = false;

	try {
		// Recover configuration left behind if a previous run was interrupted.
		try {
			await readFile( statePath, 'utf8' );
			configurationMayBeMutated = true;
		} catch ( error ) {
			if ( error.code !== 'ENOENT' ) {
				throw error;
			}
		}
		if ( configurationMayBeMutated ) {
			await restoreSavedConfiguration();
			configurationMayBeMutated = false;
		}

		const localDir = getLocalDir();
		const originalDbName = wpCli(
			[ 'config', 'get', 'DB_NAME', '--type=constant' ],
			localDir,
			ROOT_CONFIG_FILE
		);
		if ( ! /^[A-Za-z0-9_-]+$/.test( originalDbName ) ) {
			throw new Error(
				'The development database name is not safe to change for E2E tests.'
			);
		}

		if ( originalDbName === E2E_DB_NAME ) {
			throw new Error(
				`The development environment already uses the E2E database (${ E2E_DB_NAME }). Refusing to reset it.`
			);
		}

		const originalUploads = getConfigConstant( 'UPLOADS', localDir );
		const originalCacheKeySalt = getConfigConstant(
			'WP_CACHE_KEY_SALT',
			localDir
		);
		const originalTablePrefix = getConfigVariable(
			'table_prefix',
			localDir
		);
		if ( ! originalTablePrefix || ! /^[A-Za-z0-9_]+$/.test( originalTablePrefix ) ) {
			throw new Error(
				'The development table prefix is not safe to copy to the E2E configuration.'
			);
		}

		const state = {
			lockId,
			localDir,
			configurationMode: 'separate',
			originalDbName,
			originalTablePrefix,
			originalUploads,
			originalCacheKeySalt,
		};

		// Recreate the dedicated database through WordPress's existing connection.
		// The MySQL CLI may not trust the self-signed certificate in CI.
		const resetDatabaseCommand = `global $wpdb; foreach ( array( "DROP DATABASE IF EXISTS ${ E2E_DB_NAME }", "CREATE DATABASE ${ E2E_DB_NAME }" ) as $query ) { if ( false === $wpdb->query( $query ) ) { WP_CLI::error( $wpdb->last_error ); } }`;
		wpCli(
			[ 'eval', resetDatabaseCommand, `--url=http://localhost:${ process.env.LOCAL_PORT || '8889' }` ],
			localDir,
			ROOT_CONFIG_FILE
		);

		const temporaryStatePath = `${ statePath }.${ lockId }.tmp`;
		await writeFile( temporaryStatePath, JSON.stringify( state ), 'utf8' );
		await rename( temporaryStatePath, statePath );
		configurationMayBeMutated = true;
		await createE2EConfig( localDir );
		await addE2EConfigRouter();
		wpCli(
			[
				'core',
				'install',
				`--url=${ new URL( baseURL ).origin }`,
				'--title=WordPress E2E Tests',
				`--admin_user=${ process.env.WP_USERNAME || 'admin' }`,
				`--admin_password=${ process.env.WP_PASSWORD || 'password' }`,
				'--admin_email=admin@example.com',
				'--skip-email',
			],
			localDir,
			E2E_CONFIG_FILE
		);
	} catch ( error ) {
		if ( configurationMayBeMutated ) {
			try {
				await restoreSavedConfiguration();
				configurationMayBeMutated = false;
			} catch ( restoreError ) {
				error.message += ` Configuration restore also failed: ${ restoreError.message }`;
				throw error;
			}
		}

		await releaseLock();
		throw error;
	}

	return true;
}

/**
 * Restore the site's original database, uploads, and cache configuration.
 *
 * @return {Promise<void>}
 */
async function restoreE2EDatabase() {
	let state;
	try {
		state = JSON.parse( await readFile( statePath, 'utf8' ) );
	} catch ( error ) {
		if ( error.code === 'ENOENT' ) {
			await releaseLock();
			return;
		}

		throw error;
	}
	if ( ! state || typeof state.lockId !== 'string' ) {
		throw new Error( 'Invalid saved E2E database lock; refusing to restore it.' );
	}

	const owner = await readLock();
	if ( ! owner || owner.id !== state.lockId ) {
		throw new Error(
			'Cannot restore E2E configuration because this process does not own the database lock.'
		);
	}

	if ( ! lockId && owner.pid !== process.pid ) {
		throw new Error(
			'Cannot restore E2E configuration from a process that does not own the database lock.'
		);
	}

	await restoreSavedConfiguration();
	await releaseLock();
}

module.exports = {
	getBaseURL,
	isDefaultEnvironment,
	restoreE2EDatabase,
	setupE2EDatabase,
};
