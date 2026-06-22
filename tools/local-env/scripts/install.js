/* jshint node:true */

const dotenv       = require( 'dotenv' );
const dotenvExpand = require( 'dotenv-expand' );
const wait_on = require( 'wait-on' );
const { execSync } = require( 'child_process' );
const { readFileSync, writeFileSync } = require( 'fs' );
const local_env_utils = require( './utils' );

dotenvExpand.expand( dotenv.config() );

// Create wp-config.php.
wp_cli( `config create --dbname=wordpress_develop --dbuser=root --dbpass=password --dbhost=mysql --force --config-file="wp-config.php"` );

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
	const wp_cli_command = `npm --silent run env:cli -- ${cmd} --path=/var/www/${process.env.LOCAL_DIR}`;

	for ( let attempt = 1; attempt <= 5; attempt++ ) {
		try {
			const output = execSync( wp_cli_command, {
				encoding: 'utf8',
				stdio: [ 'ignore', 'pipe', 'pipe' ],
			} );

			if ( output ) {
				process.stdout.write( output );
			}

			return;
		} catch ( error ) {
			if ( error.stdout ) {
				process.stdout.write( error.stdout.toString() );
			}

			if ( error.stderr ) {
				process.stderr.write( error.stderr.toString() );
			}

			if ( 5 === attempt || ! wp_cli_container_is_starting( error ) ) {
				throw error;
			}

			const delay = attempt * 1000;
			console.warn( `The WP-CLI container is still starting, retrying in ${ delay / 1000 } second(s)...` );
			sleep( delay );
		}
	}
}

/**
 * Determines whether the WP-CLI container is still starting.
 *
 * @param {Error & {stdout?: Buffer|string, stderr?: Buffer|string}} error Error thrown by execSync().
 * @return {boolean} Whether the CLI container is still starting up.
 */
function wp_cli_container_is_starting( error ) {
	const output = `${ error.stdout || '' }\n${ error.stderr || '' }\n${ error.message || '' }`;

	return output.includes( 'service "cli" is not running' );
}

/**
 * Sleeps synchronously for a specified number of milliseconds.
 *
 * @param {number} milliseconds The number of milliseconds to sleep.
 */
function sleep( milliseconds ) {
	Atomics.wait( new Int32Array( new SharedArrayBuffer( 4 ) ), 0, 0, milliseconds );
}
