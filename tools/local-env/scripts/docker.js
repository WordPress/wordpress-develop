const dotenv       = require( 'dotenv' );
const dotenvExpand = require( 'dotenv-expand' );
const { execSync } = require( 'child_process' );

dotenvExpand.expand( dotenv.config() );

// Execute any docker-compose command passed to this script.
execSync( 'docker-compose ' + process.argv.slice( 2 ).join( ' ' ), { stdio: 'inherit' } );
