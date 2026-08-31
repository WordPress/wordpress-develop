/* jshint node:true */

const dotenv = require( 'dotenv' );
const dotenvExpand = require( 'dotenv-expand' );
const { spawnSync } = require( 'child_process' );
const local_env_utils = require( './utils' );

local_env_utils.ensure_env_file();

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

// Add a --defaults flag to any db command WP-CLI command. See https://core.trac.wordpress.org/ticket/63876.
if ( dockerCommand.includes( 'cli' ) && dockerCommand.includes( 'db' ) && ! dockerCommand.includes( '--defaults' ) ) {
	dockerCommand.push( '--defaults' );
}

const composeArgs = [
	'compose',
	...composeFiles
		.map( ( composeFile ) => [ '-f', composeFile ] )
		.flat(),
	...dockerCommand,
];

// Failures during image pulls are re-attempted to rule out registry rate limits and network issues.
const maxAttempts = 'pull' === dockerCommand[0] ? 3 : 1;

// Execute any Docker compose command passed to this script.
let returns;
for ( let attempt = 1; attempt <= maxAttempts; attempt++ ) {
	returns = spawnSync( 'docker', composeArgs, { stdio: 'inherit' } );

	if ( 0 === returns.status ) {
		break;
	}

	if ( attempt === maxAttempts ) {
		if ( maxAttempts > 1 ) {
			console.log( `\ndocker compose ${ dockerCommand[0] } failed after ${ attempt } attempts.` );
		}

		break;
	}

	const delay = attempt * 10;
	console.log( `\ndocker compose ${ dockerCommand[0] } failed (attempt ${ attempt } of ${ maxAttempts }). Retrying in ${ delay } seconds...\n` );

	// Sleep synchronously so the retry loop stays in order without going async.
	Atomics.wait( new Int32Array( new SharedArrayBuffer( 4 ) ), 0, 0, delay * 1000 );
}

if ( returns.error ) {
	console.error( `Could not run Docker Compose. ${ returns.error.message }` );
} else if ( returns.signal && returns.signal !== 'SIGINT' ) {
	console.error( `Docker Compose was terminated by ${ returns.signal }.` );
}

// `status` is null when Docker could not be spawned at all, or was killed by a signal. SIGINT is
// how a long-running command such as `env:logs` is normally ended, so it is not a failure worth an
// npm error block. Every other signal means the command was killed before it finished.
process.exit( returns.signal === 'SIGINT' ? 0 : ( returns.status ?? 1 ) );
