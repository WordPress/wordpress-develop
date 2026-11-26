/**
 * External dependencies
 */
const { randomUUID } = require( 'node:crypto' );
const { spawnSync } = require( 'node:child_process' );
const {
	link,
	mkdir,
	open,
	readFile,
	rm,
	rename,
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
const E2E_DIRECTORY = path.join( process.cwd(), 'tests/e2e' );
const statePath = path.join( E2E_DIRECTORY, '.database-state.json' );
const lockPath = path.join( E2E_DIRECTORY, '.database-lock' );
const recoveryLockPath = `${ lockPath }.recovery`;

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
	return new URL(
		process.env.WP_BASE_URL ||
		`http://localhost:${ process.env.LOCAL_PORT || '8889' }`
	).href;
}

/**
 * Run a WP-CLI command in the local development environment.
 *
 * @param {string[]} args    WP-CLI arguments.
 * @param {string}   localDir WordPress document root.
 * @return {{ status: number, stdout: string, stderr: string }} WP-CLI command result.
 */
function runWpCli( args, localDir = getLocalDir() ) {
	const result = spawnSync(
		process.execPath,
		[
			path.join( process.cwd(), 'tools/local-env/scripts/docker.js' ),
			'exec',
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
 * @param {string[]} args     WP-CLI arguments.
 * @param {string}   localDir WordPress document root.
 * @return {string} Command output.
 */
function wpCli( args, localDir = getLocalDir() ) {
	const result = runWpCli( args, localDir );

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
 * @param {string}   name     Constant name.
 * @param {string}   localDir WordPress document root.
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
 * Check whether the URL uses Core's default local test environment.
 *
 * @param {string} baseURL Test environment URL.
 * @return {boolean} Whether the URL points to the configured local environment.
 */
function isDefaultEnvironment( baseURL ) {
	const url = new URL( baseURL );
	const port =
		url.port || ( url.protocol === 'https:' ? '443' : '80' );
	const localPort = process.env.LOCAL_PORT || '8889';

	return (
		[ 'localhost', '127.0.0.1' ].includes( url.hostname ) &&
		url.protocol === 'http:' &&
		port === localPort
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
 * @return {Promise<?{ pid: number, id: string }>} Current lock owner.
 */
async function readLock() {
	try {
		const owner = JSON.parse( await readFile( lockPath, 'utf8' ) );
		if ( ! Number.isInteger( owner.pid ) || typeof owner.id !== 'string' ) {
			throw new Error( 'Invalid E2E database lock.' );
		}

		return owner;
	} catch ( error ) {
		if ( error.code === 'ENOENT' ) {
			return null;
		}

		throw new Error(
			`Cannot safely read the E2E database lock. ${ error.message }`
		);
	}
}

/**
 * Create a lock file with complete owner data using an atomic hard link.
 *
 * @param {{ pid: number, id: string }} owner Lock owner.
 * @return {Promise<void>}
 */
async function createLock( owner ) {
	const temporaryPath = `${ lockPath }.${ owner.id }.tmp`;
	await writeFile( temporaryPath, JSON.stringify( owner ), { flag: 'wx' } );
	try {
		await link( temporaryPath, lockPath );
	} finally {
		await rm( temporaryPath, { force: true } );
	}
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

	let recoveryHandle;
	try {
		recoveryHandle = await open( recoveryLockPath, 'wx' );
	} catch ( error ) {
		if ( error.code === 'EEXIST' ) {
			throw new Error(
				'Another process is recovering an interrupted E2E run. Wait for it to finish before starting another run.'
			);
		}
		throw error;
	}

	try {
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
		await recoveryHandle.close();
		await rm( recoveryLockPath, { force: true } );
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
		typeof state.originalDbName !== 'string' ||
		! /^[A-Za-z0-9_-]+$/.test( state.originalDbName )
	) {
		throw new Error(
			'Invalid saved database configuration; refusing to restore it.'
		);
	}
	if (
		state.originalUploads !== null &&
		typeof state.originalUploads !== 'string'
	) {
		throw new Error(
			'Invalid saved uploads configuration; refusing to restore it.'
		);
	}
	if (
		state.originalCacheKeySalt !== null &&
		typeof state.originalCacheKeySalt !== 'string'
	) {
		throw new Error(
			'Invalid saved object-cache configuration; refusing to restore it.'
		);
	}

	const localDir = getLocalDir( state.localDir );

	wpCli(
		[ 'config', 'set', 'DB_NAME', state.originalDbName, '--type=constant' ],
		localDir
	);

	if ( state.originalUploads === null ) {
		wpCli( [ 'config', 'delete', 'UPLOADS', '--type=constant' ], localDir );
	} else {
		wpCli(
			[ 'config', 'set', 'UPLOADS', state.originalUploads, '--type=constant' ],
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
			localDir
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

		const state = {
			lockId,
			localDir,
			originalDbName,
			originalUploads,
			originalCacheKeySalt,
		};

		// Create the dedicated database without touching the PHPUnit database.
		wpCli(
			[ 'db', 'query', `CREATE DATABASE IF NOT EXISTS ${ E2E_DB_NAME }` ],
			localDir
		);

		const temporaryStatePath = `${ statePath }.${ lockId }.tmp`;
		await writeFile( temporaryStatePath, JSON.stringify( state ), 'utf8' );
		await rename( temporaryStatePath, statePath );
		configurationMayBeMutated = true;
		wpCli(
			[ 'config', 'set', 'DB_NAME', E2E_DB_NAME, '--type=constant' ],
			localDir
		);
		wpCli(
			[ 'config', 'set', 'UPLOADS', E2E_UPLOADS, '--type=constant' ],
			localDir
		);
		wpCli(
			[
				'config',
				'set',
				'WP_CACHE_KEY_SALT',
				`e2e-${ randomUUID() }-`,
				'--type=constant',
			],
			localDir
		);
		wpCli( [ 'db', 'reset', '--yes' ], localDir );
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
			localDir
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
