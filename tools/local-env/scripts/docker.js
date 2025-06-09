/* jshint node:true */

const dotenv = require( 'dotenv' );
const dotenvExpand = require( 'dotenv-expand' );
const { spawnSync } = require( 'child_process' );
const local_env_utils = require( './utils' );

dotenvExpand.expand( dotenv.config() );

const composeFiles = local_env_utils.get_compose_files();

if ( process.argv.includes( '--coverage-html' ) ) {
	process.env.LOCAL_PHP_XDEBUG = 'true';
	process.env.LOCAL_PHP_XDEBUG_MODE = 'coverage';
}

// Add --no-TTY (-T) arg after exec and run commands when STDIN is not a TTY.
const dockerCommand = process.argv.slice( 2 );
if ( [ 'exec', 'run' ].includes( dockerCommand[0] ) && ! process.stdin.isTTY ) {
	dockerCommand.splice( 1, 0, '--no-TTY' );
}

// Execute any Docker compose command passed to this script.
const returns = spawnSync(
	'docker',
	[
		'compose',
		...composeFiles
			.map( ( composeFile ) => [ '-f', composeFile ] )
			.flat(),
		...dockerCommand,
	],
	{ stdio: 'inherit' }
);

process.exit( returns.status );
