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

if ( returns.error ) {
	console.error( `Could not run Docker Compose. ${ returns.error.message }` );
} else if ( returns.signal && returns.signal !== 'SIGINT' ) {
	console.error( `Docker Compose was terminated by ${ returns.signal }.` );
}

// `status` is null when Docker could not be spawned at all, or was killed by a signal. SIGINT is
// how a long-running command such as `env:logs` is normally ended, so it is not a failure worth an
// npm error block. Every other signal means the command was killed before it finished.
process.exit( returns.signal === 'SIGINT' ? 0 : ( returns.status ?? 1 ) );
