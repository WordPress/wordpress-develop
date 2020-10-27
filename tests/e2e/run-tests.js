const dotenv = require( 'dotenv' );
const dotenv_expand = require( 'dotenv-expand' );
const { sync: spawn } = require( 'cross-spawn' );
const { execSync } = require( 'child_process' );

// WP_BASE_URL interpolates LOCAL_PORT, so needs to be parsed by dotenv_expand().
dotenv_expand( dotenv.config() );

const result = spawn( 'node', [ require.resolve( 'puppeteer/install' ) ], {
	stdio: 'inherit',
} );
if ( result.status > 0 ) {
	process.exit( result.status );
}

// Run the tests, passing additional arguments through to the test script.
execSync( 'wp-scripts test-e2e --config tests/e2e/jest.config.js ' + process.argv.slice( 2 ).join( ' ' ), { stdio: 'inherit' } );
