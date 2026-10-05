/* jshint node:true */

const dotenv = require( 'dotenv' );
const dotenvExpand = require( 'dotenv-expand' );
const local_env_utils = require( './utils' );

local_env_utils.ensure_env_file();

dotenvExpand.expand( dotenv.config() );

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

// Failures during image pulls are re-attempted to rule out registry rate limits and network issues.
// `composer install` and `composer update` reach repo.packagist.org for the same reason, and both
// are safe to repeat. Every other Composer command runs once, so a failure such as a PHPStan error
// is reported as the real result it is.
const composerArgs = dockerCommand.slice( dockerCommand.indexOf( 'composer' ) + 1 );
let composerCommand;

for ( let i = 0; i < composerArgs.length; i++ ) {
	// Global options precede the command. `--working-dir` is the only one that takes a separate
	// value, so it is the only value that could otherwise be mistaken for the command itself.
	if ( '-d' === composerArgs[i] || '--working-dir' === composerArgs[i] ) {
		i++;
	} else if ( ! composerArgs[i].startsWith( '-' ) ) {
		composerCommand = composerArgs[i];
		break;
	}
}

const retryable = 'pull' === dockerCommand[0] ||
	( 'run' === dockerCommand[0] && dockerCommand.includes( 'composer' ) &&
		[ 'install', 'update' ].includes( composerCommand ) );

// Execute any Docker compose command passed to this script.
const returns = local_env_utils.compose_with_retry( dockerCommand, retryable ? 3 : 1 );

if ( returns.error ) {
	console.error( `Could not run Docker Compose. ${ returns.error.message }` );
} else if ( returns.signal && returns.signal !== 'SIGINT' ) {
	console.error( `Docker Compose was terminated by ${ returns.signal }.` );
}

// `status` is null when Docker could not be spawned at all, or was killed by a signal. Ctrl-C
// signals the whole process group, so this script usually dies alongside Compose without reaching
// here. This covers a signal sent to Compose alone: SIGINT means the command was cancelled, as
// when ending `env:logs`, and every other signal means it was killed before it finished.
process.exit( returns.signal === 'SIGINT' ? 0 : ( returns.status ?? 1 ) );
