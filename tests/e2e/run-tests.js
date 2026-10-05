const dotenv = require( 'dotenv' );
const dotenvExpand = require( 'dotenv-expand' );
const { spawn } = require( 'child_process' );
const { copyFileSync, mkdirSync, rmSync } = require( 'fs' );
const path = require( 'path' );

// WP_BASE_URL interpolates LOCAL_PORT, so needs to be parsed by dotenvExpand().
dotenvExpand.expand( dotenv.config() );

// Keep the login screen's delayed autofocus from interrupting automated input.
const muPluginDirectory = path.join(
	process.env.LOCAL_DIR || 'src',
	'wp-content',
	'mu-plugins'
);
const muPluginFile = path.join(
	muPluginDirectory,
	`e2e-disable-login-autofocus-${ process.pid }.php`
);

mkdirSync( muPluginDirectory, { recursive: true } );
copyFileSync(
	path.join( __dirname, 'mu-plugins', 'disable-login-autofocus.php' ),
	muPluginFile
);

// Remove only this run's fixture, including on failure or interruption.
process.once( 'exit', () => rmSync( muPluginFile, { force: true } ) );

// Run the tests, passing additional arguments through to the test script.
const tests = spawn(
	process.execPath,
	[
		require.resolve( '@wordpress/scripts/bin/wp-scripts.js' ),
		'test-e2e',
		'--config',
		'tests/e2e/jest.config.js',
		...process.argv.slice( 2 ),
	],
	{ stdio: 'inherit', detached: process.platform !== 'win32' }
);

for ( const signal of [ 'SIGHUP', 'SIGINT', 'SIGQUIT', 'SIGTERM' ] ) {
	process.on( signal, () => {
		// Stop the test process group before removing its fixture.
		try {
			if ( process.platform === 'win32' ) {
				tests.kill( signal );
			} else {
				process.kill( -tests.pid, signal );
			}
		} catch ( error ) {
			if ( error.code !== 'ESRCH' ) {
				throw error;
			}
		}
	} );
}

tests.once( 'error', ( error ) => {
	throw error;
} );
tests.once( 'exit', ( code, signal ) => {
	const signalExitCodes = {
		SIGHUP: 129,
		SIGINT: 130,
		SIGQUIT: 131,
		SIGTERM: 143,
	};
	process.exitCode = code === null ? signalExitCodes[ signal ] || 1 : code;
} );
