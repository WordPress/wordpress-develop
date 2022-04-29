const dotenv       = require( 'dotenv' );
const dotenvExpand = require( 'dotenv-expand' );
const { execSync } = require( 'child_process' );

if ( process.arch === 'arm64' ) {
	process.env.LOCAL_DB_ARCHITECTURE = 'amd64/';
}

dotenvExpand.expand( dotenv.config() );

// Execute any docker-compose command passed to this script.
execSync( 'docker-compose ' + process.argv.slice( 2 ).join( ' ' ), { stdio: 'inherit' } );
