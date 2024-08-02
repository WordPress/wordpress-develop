const dotenv       = require( 'dotenv' );
const dotenvExpand = require( 'dotenv-expand' );
const { execSync } = require( 'child_process' );
const local_env_utils = require( './utils' );

dotenvExpand.expand( dotenv.config() );

local_env_utils.determine_compose_files();

// Execute any docker compose command passed to this script.
execSync( 'docker compose ' + process.env.LOCAL_COMPOSE_FILE + ' ' + process.argv.slice( 2 ).join( ' ' ), { stdio: 'inherit' } );
