/* jshint node:true */

const dotenv       = require( 'dotenv' );
const dotenvExpand = require( 'dotenv-expand' );
const wait_on = require( 'wait-on' );
const { execSync } = require( 'child_process' );
const { readFileSync, writeFileSync } = require( 'fs' );
const local_env_utils = require( './utils' );

dotenvExpand.expand( dotenv.config() );

// Create wp-config.php. Use retry logic to handle database startup race conditions.
wp_cli_with_retry( `config create --dbname=wordpress_develop --dbuser=root --dbpass=password --dbhost=mysql --force --config-file="wp-config.php"` );

// Add the debug settings to wp-config.php.
// Windows requires this to be done as an additional step, rather than using the --extra-php option in the previous step.
wp_cli( `config set WP_DEBUG ${process.env.LOCAL_WP_DEBUG} --raw --type=constant` );
wp_cli( `config set WP_DEBUG_LOG ${process.env.LOCAL_WP_DEBUG_LOG} --raw --type=constant` );
wp_cli( `config set WP_DEBUG_DISPLAY ${process.env.LOCAL_WP_DEBUG_DISPLAY} --raw --type=constant` );
wp_cli( `config set SCRIPT_DEBUG ${process.env.LOCAL_SCRIPT_DEBUG} --raw --type=constant` );
wp_cli( `config set WP_ENVIRONMENT_TYPE ${process.env.LOCAL_WP_ENVIRONMENT_TYPE} --type=constant` );
wp_cli( `config set WP_DEVELOPMENT_MODE ${process.env.LOCAL_WP_DEVELOPMENT_MODE} --type=constant` );

// Read in wp-tests-config-sample.php, edit it to work with our config, then write it to wp-tests-config.php.
const testConfig = readFileSync( 'wp-tests-config-sample.php', 'utf8' )
	.replace( 'youremptytestdbnamehere', 'wordpress_develop_tests' )
	.replace( 'yourusernamehere', 'root' )
	.replace( 'yourpasswordhere', 'password' )
	.replace( 'localhost', 'mysql' )
	.replace( `'WP_TESTS_DOMAIN', 'example.org'`, `'WP_TESTS_DOMAIN', '${process.env.LOCAL_WP_TESTS_DOMAIN}'` )
	.concat( `\ndefine( 'FS_METHOD', 'direct' );\n` );

writeFileSync( 'wp-tests-config.php', testConfig );

// Once the site is available, install WordPress!
wait_on( {
	resources: [ `tcp:localhost:${process.env.LOCAL_PORT}`],
	timeout: 3000,
} )
	.catch( err => {
		console.error( `Error: It appears the development environment has not been started. Message: ${ err.message }` );
		console.error( `Did you forget to do 'npm run env:start'?` );
		process.exit( 1 );
	} )
	.then( () => {
		wp_cli( 'db reset --yes --defaults' );
		const installCommand = process.env.LOCAL_MULTISITE === 'true'  ? 'multisite-install' : 'install';
		wp_cli( `core ${ installCommand } --title="WordPress Develop" --admin_user=admin --admin_password=password --admin_email=test@example.com --skip-email --url=http://localhost:${process.env.LOCAL_PORT}` );
		wp_cli( `rewrite structure '/%year%/%monthnum%/%postname%/'` );
	} )
	.catch( err => {
		console.error( `Error: Unable to reset DB and install WordPress. Message: ${ err.message }` );
		process.exit( 1 );
	} );

/**
 * Runs WP-CLI commands in the Docker environment.
 *
 * @param {string} cmd The WP-CLI command to run.
 */
function wp_cli( cmd ) {
	execSync( `npm --silent run env:cli -- ${cmd} --path=/var/www/${process.env.LOCAL_DIR}`, { stdio: 'inherit' } );
}

/**
 * Synchronously pauses execution for the given number of milliseconds.
 *
 * Uses `Atomics.wait` so the pause works on every platform. Shelling out to a
 * `sleep` command would fail on Windows, where `sleep` is not a `cmd.exe` builtin.
 *
 * @param {number} ms Milliseconds to wait.
 */
function sleep_sync( ms ) {
	Atomics.wait( new Int32Array( new SharedArrayBuffer( 4 ) ), 0, 0, ms );
}

/**
 * Runs WP-CLI commands with retry-on-failure to ride out database startup races.
 *
 * The Docker `mysqladmin ping` healthcheck can report MariaDB ready before the
 * server accepts authenticated connections, causing `wp config create` to fail
 * intermittently with "Database connection error (2002) Connection refused".
 *
 * Because `wp_cli` invokes `execSync` with `stdio: 'inherit'`, the caught error
 * does not carry the underlying stderr that would let us classify the failure.
 * Every failure is therefore treated as potentially transient and retried; the
 * underlying wp-cli error is still streamed live to the user on each attempt,
 * and a persistent failure surfaces the same error after the retry envelope
 * is exhausted.
 *
 * Backoff schedule with the defaults: 1s, 2s, 4s, 8s, 16s (~31s total) before
 * the final attempt.
 *
 * @param {string} cmd        The WP-CLI command to run.
 * @param {number} maxRetries Maximum number of attempts. Default 6.
 * @param {number} delay      Initial backoff delay in milliseconds. Default 1000.
 */
function wp_cli_with_retry( cmd, maxRetries = 6, delay = 1000 ) {
	for ( let i = 0; i < maxRetries; i++ ) {
		try {
			wp_cli( cmd );
			return;
		} catch ( err ) {
			if ( i === maxRetries - 1 ) {
				const total_wait_seconds = ( delay * ( Math.pow( 2, maxRetries - 1 ) - 1 ) ) / 1000;
				console.error( `Command failed after ${ maxRetries } attempts (~${ total_wait_seconds }s of retries). Likely a persistent database or configuration issue.` );
				throw err;
			}
			const waitTime = delay * Math.pow( 2, i );
			console.log( `Command failed (likely transient database startup race). Retrying in ${ waitTime }ms... (attempt ${ i + 1 }/${ maxRetries })` );
			sleep_sync( waitTime );
		}
	}
}
