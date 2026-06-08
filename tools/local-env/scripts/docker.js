const dotenv       = require( 'dotenv' );
const dotenvExpand = require( 'dotenv-expand' );
const { execSync } = require( 'child_process' );
const local_env_utils = require( './utils' );

dotenvExpand.expand( dotenv.config() );

const composeFiles = local_env_utils.get_compose_files();

if (process.argv.includes('--coverage-html')) {
	process.env.LOCAL_PHP_XDEBUG = 'true';
	process.env.LOCAL_PHP_XDEBUG_MODE = 'coverage';
}

// Execute any docker compose command passed to this script.
execSync( 'docker compose ' + composeFiles + ' ' + process.argv.slice( 2 ).join( ' ' ), { stdio: 'inherit' } );
