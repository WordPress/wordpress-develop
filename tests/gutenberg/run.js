#!/usr/bin/env node
const path = require( 'path' );
const { v4: uuid } = require( 'uuid' );
const os = require( 'os' );
const fs = require( 'fs' );
const chalk = require( 'chalk' );
const childProcess = require( 'child_process' );

// Config
const GUTENBERG_VERSION = 'wp/trunk';

// Utils
/**
 * Utility to run a child script
 *
 * @param {string} script Script to run.
 * @param {string=} cwd   Working directory.
 */
function runShellScript( script, cwd ) {
	childProcess.execSync( script, {
		cwd,
		env: {
			NO_CHECKS: 'true',
			PATH: process.env.PATH,
			HOME: process.env.HOME,
		},
		stdio: [ 'inherit', 'ignore', 'inherit' ],
	} );
}

/**
 * Small utility used to read an uncached version of a JSON file
 *
 * @param {string} fileName
 */
function readJSONFile( fileName ) {
	const data = fs.readFileSync( fileName, 'utf8' );
	return JSON.parse( data );
}

/**
 * Generates a random temporary path in the OS's tmp dir.
 *
 * @return {string} Temporary Path.
 */
function getRandomTemporaryPath() {
	return path.join( os.tmpdir(), uuid() );
}

// Useful constants
const title = chalk.bold;
const success = chalk.bold.green;
const rootFolder = path.resolve( __dirname, '../../' );
const testLauncherPath = getRandomTemporaryPath();
const testEnvironmentPath = getRandomTemporaryPath();
const sampleEnvConfig = path.resolve( __dirname, '.wp-env.sample.json' );

// Welcome
console.log( title( '>> 🏁 Welcome, this command is going to prepare a running WordPress environment and run the Gutenberg e2e tests against it.' ) );
console.log( title( '>> It uses the current WordPress folder from which it\'s being run and a separate Gutenberg clone performed by the command.' ) );
console.log( title( '>> Configuration: ' ) );
console.log( 'Gutenberg Version: ' + success( GUTENBERG_VERSION ) );
console.log( 'Test Launcher Path (Gutenberg): ' + success( testLauncherPath ) );
console.log( 'Test Environment Path (wp-env): ' + success( testEnvironmentPath ) );

// Steps
// 1- Preparing the WordPress environment
console.log( '>> Preparing the WordPress clone' );
runShellScript( 'npm install && FORCE_REDUCED_MOTION=true npm run build', rootFolder );

// 2- Preparing the Gutenberg clone
// The tests and the launcher comes from the Gutenberg repository e2e tests
console.log( title( '>> Preparing the e2e tests launcher' ) );
runShellScript( 'git clone https://github.com/WordPress/gutenberg.git ' + testLauncherPath + ' --depth=1 --no-single-branch' );
runShellScript( 'git checkout ' + GUTENBERG_VERSION, testLauncherPath );
runShellScript( 'npm install && npm run build', testLauncherPath );

// 3- Running the WordPress environment using wp-env
// The environment should include the WordPress install and the e2e tests plugins.s
console.log( title( '>> Preparing the environment' ) );
runShellScript( 'mkdir -p ' + testEnvironmentPath );
const envConfig = readJSONFile( sampleEnvConfig );
envConfig.core = path.resolve( rootFolder, 'build' );
envConfig.mappings[ 'wp-content/mu-plugins' ] = path.resolve( testLauncherPath, 'packages/e2e-tests/mu-plugins' );
envConfig.mappings[ 'wp-content/plugins/test-plugins' ] = path.resolve( testLauncherPath, 'packages/e2e-tests/plugins' );
fs.writeFileSync(
	path.resolve( testEnvironmentPath, '.wp-env.json' ),
	JSON.stringify( envConfig, null, '\t' ) + '\n'
);

// 4- Starting the environment
console.log( title( '>> Starting the environment' ) );
runShellScript( path.resolve( testLauncherPath, 'node_modules/.bin/wp-env' ) + ' start ', testEnvironmentPath );

// 5- Running the tests
console.log( title( '>> Running the e2e tests' ) );
runShellScript( 'npm run test-e2e packages/e2e-tests/specs/editor', testLauncherPath );
