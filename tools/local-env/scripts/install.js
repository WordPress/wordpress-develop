const dotenv       = require( 'dotenv' );
const dotenvExpand = require( 'dotenv-expand' );
const wait_on = require( 'wait-on' );
const { execSync } = require( 'child_process' );
const { renameSync, readFileSync, writeFileSync } = require( 'fs' );
const { utils } = require( './utils.js' );
const local_env_utils = require( './utils' );

dotenvExpand.expand( dotenv.config() );

// Determine if a non-default database authentication plugin needs to be used.
local_env_utils.determine_auth_option();

// Create wp-config.php.
wp_cli( 'config create --dbname=wordpress_develop --dbuser=root --dbpass=password --dbhost=mysql --path=/var/www/src --force' );

// Add the debug settings to wp-config.php.
// Windows requires this to be done as an additional step, rather than using the --extra-php option in the previous step.
wp_cli( `config set WP_DEBUG ${process.env.LOCAL_WP_DEBUG} --raw --type=constant` );
wp_cli( `config set WP_DEBUG_LOG ${process.env.LOCAL_WP_DEBUG_LOG} --raw --type=constant` );
wp_cli( `config set WP_DEBUG_DISPLAY ${process.env.LOCAL_WP_DEBUG_DISPLAY} --raw --type=constant` );
wp_cli( `config set SCRIPT_DEBUG ${process.env.LOCAL_SCRIPT_DEBUG} --raw --type=constant` );
wp_cli( `config set WP_ENVIRONMENT_TYPE ${process.env.LOCAL_WP_ENVIRONMENT_TYPE} --type=constant` );
wp_cli( `config set WP_DEVELOPMENT_MODE ${process.env.LOCAL_WP_DEVELOPMENT_MODE} --type=constant` );

// Move wp-config.php to the base directory, so it doesn't get mixed up in the src or build directories.
renameSync( 'src/wp-config.php', 'wp-config.php' );

install_wp_importer();

// Read in wp-tests-config-sample.php, edit it to work with our config, then write it to wp-tests-config.php.
const testConfig = readFileSync( 'wp-tests-config-sample.php', 'utf8' )
	.replace( 'youremptytestdbnamehere', 'wordpress_develop_tests' )
	.replace( 'yourusernamehere', 'root' )
	.replace( 'yourpasswordhere', 'password' )
	.replace( 'localhost', 'mysql' )
	.replace( "'WP_TESTS_DOMAIN', 'example.org'", `'WP_TESTS_DOMAIN', '${process.env.LOCAL_WP_TESTS_DOMAIN}'` )
	.concat( "\ndefine( 'FS_METHOD', 'direct' );\n" );

writeFileSync( 'wp-tests-config.php', testConfig );

// Once the site is available, install WordPress!
wait_on( { resources: [ `tcp:localhost:${process.env.LOCAL_PORT}`] } )
	.then( () => {
		wp_cli( 'db reset --yes' );
		const installCommand = process.env.LOCAL_MULTISITE === 'true'  ? 'multisite-install' : 'install';
		wp_cli( `core ${ installCommand } --title="WordPress Develop" --admin_user=admin --admin_password=password --admin_email=test@test.com --skip-email --url=http://localhost:${process.env.LOCAL_PORT}` );
	} );

/**
 * Runs WP-CLI commands in the Docker environment.
 *
 * @param {string} cmd The WP-CLI command to run.
 */
function wp_cli( cmd ) {
	const composeFiles = local_env_utils.get_compose_files();

	execSync( `docker compose ${composeFiles} run --rm cli ${cmd}`, { stdio: 'inherit' } );
}

/**
 * Downloads the WordPress Importer plugin for use in tests.
 */
function install_wp_importer() {
	const testPluginDirectory = 'tests/phpunit/data/plugins/wordpress-importer';
	const composeFiles = local_env_utils.get_compose_files();

	execSync( `docker compose ${composeFiles} exec -T php rm -rf ${testPluginDirectory}`, { stdio: 'inherit' } );
	execSync( `docker compose ${composeFiles} exec -T php git clone https://github.com/WordPress/wordpress-importer.git ${testPluginDirectory} --depth=1`, { stdio: 'inherit' } );
}
