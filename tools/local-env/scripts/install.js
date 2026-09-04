/* jshint node:true */

const dotenv       = require( 'dotenv' );
const dotenvExpand = require( 'dotenv-expand' );
const wait_on = require( 'wait-on' );
const { execSync } = require( 'child_process' );
const { readFileSync, writeFileSync } = require( 'fs' );
const local_env_utils = require( './utils' );

local_env_utils.ensure_env_file();

dotenvExpand.expand( dotenv.config() );

// Create wp-config.php. This verifies the database connection, so retrying it doubles as the
// readiness probe: the mysql healthcheck pings the container's own socket, which the temporary
// server used to initialise a cold volume answers before the real server listens on TCP.
wp_cli_retry(
	`config create --dbname=wordpress_develop --dbuser=root --dbpass=password --dbhost=mysql --force --config-file="wp-config.php"`,
	{
		// Initialising a cold database volume takes well over the 3 second web server timeout
		// used below, and longer still on a loaded CI runner.
		timeout: 120000,
		waiting: 'Waiting for the database to accept connections...',
		failure: 'The database did not accept connections',
		hint: `Check the container logs with 'npm run env:logs mysql'.`,
	}
);

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
 * @param {string} cmd   The WP-CLI command to run.
 * @param {string} stdio How to handle the command's output. Defaults to 'inherit'.
 */
function wp_cli( cmd, stdio = 'inherit' ) {
	return execSync( `npm --silent run env:cli -- ${cmd} --path=/var/www/${process.env.LOCAL_DIR}`, { stdio } );
}

/**
 * Runs a WP-CLI command, retrying it until it succeeds or the timeout is reached.
 *
 * Exits with an error when the timeout is reached, or as soon as the failure is one that
 * retrying cannot fix.
 *
 * @param {string} cmd             The WP-CLI command to run.
 * @param {Object} options
 * @param {number} options.timeout How long to keep retrying for, in milliseconds.
 * @param {string} options.waiting Message shown when the command has to be retried.
 * @param {string} options.failure Reason reported when the timeout is reached.
 * @param {string} options.hint    Suggested next step when the timeout is reached.
 */
function wp_cli_retry( cmd, { timeout, waiting, failure, hint } ) {
	const interval = 2000;
	const deadline = Date.now() + timeout;
	let notified   = false;

	for ( ;; ) {
		try {
			process.stdout.write( wp_cli( cmd, 'pipe' ) );
			return;
		} catch ( err ) {
			// `stderr` and `stdout` are buffers, which are truthy even when empty, so use the
			// first one that actually captured something.
			const output = [ err.stderr, err.stdout, err.message ]
				.map( ( value ) => ( value ? value.toString().trim() : '' ) )
				.find( ( value ) => value !== '' ) || 'No output was captured.';

			// Retrying only helps while the environment is still starting up. A missing container
			// means it was never started, so there is nothing to wait for.
			if ( output.includes( 'is not running' ) ) {
				console.error( output );
				console.error( `Error: It appears the development environment has not been started.` );
				console.error( `Did you forget to do 'npm run env:start'?` );
				process.exit( 1 );
			}

			if ( ! notified ) {
				notified = true;
				console.log( waiting );
			}

			if ( Date.now() >= deadline ) {
				console.error( output );
				console.error( `Error: ${ failure } within ${ timeout / 1000 } seconds.` );
				console.error( hint );
				process.exit( 1 );
			}

			// Sleep synchronously, so the retries stay in front of the commands that follow.
			Atomics.wait( new Int32Array( new SharedArrayBuffer( 4 ) ), 0, 0, interval );
		}
	}
}
