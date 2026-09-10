/* jshint node:true */

const { spawnSync } = require( 'node:child_process' );
const { constants, copyFileSync, existsSync } = require( 'node:fs' );
const { join } = require( 'node:path' );

const repo_root = join( __dirname, '..', '..', '..' );

const local_env_utils = {

	/**
	 * Creates the .env file from .env.example when one is not present.
	 *
	 * Docker Compose reads this file to resolve the image tags, so it must exist before any
	 * Compose command runs, not just before the containers are started.
	 */
	ensure_env_file: function() {
		try {
			copyFileSync( join( repo_root, '.env.example' ), join( repo_root, '.env' ), constants.COPYFILE_EXCL );
		} catch ( e ) {
			// A .env that is already there is the common case and needs no warning. Any other
			// failure means the scripts run without the settings from .env, which is worth
			// reporting, but is never a reason to refuse to run a command such as `env:stop`.
			if ( e.code !== 'EEXIST' ) {
				console.warn( `Could not create a .env file from .env.example. ${ e.message }` );
			}
		}
	},

	/**
	 * Runs a Docker Compose command, re-attempting it when it fails.
	 *
	 * Any command that reaches a registry can fail for reasons that clear on their own, such as
	 * rate limits and transient network errors.
	 *
	 * @param {string[]} args     The Compose command and its arguments, such as `[ 'pull' ]`.
	 * @param {number}   attempts How many times to run the command before giving up.
	 *
	 * @return {Object} The result of the last attempt.
	 */
	compose_with_retry: function( args, attempts ) {
		const composeArgs = [
			'compose',
			...local_env_utils.get_compose_files()
				.map( ( composeFile ) => [ '-f', composeFile ] )
				.flat(),
			...args,
		];

		let returns;

		for ( let attempt = 1; attempt <= attempts; attempt++ ) {
			returns = spawnSync( 'docker', composeArgs, { stdio: 'inherit' } );

			if ( 0 === returns.status ) {
				break;
			}

			// A command killed by a signal was cancelled rather than failed, and a command that
			// could not be spawned at all fails the same way every time. Do not run either again.
			if ( returns.signal || returns.error ) {
				break;
			}

			if ( attempt === attempts ) {
				if ( attempts > 1 ) {
					console.log( `\ndocker compose ${ args[0] } failed after ${ attempt } attempts.` );
				}

				break;
			}

			const delay = attempt * 10;
			console.log( `\ndocker compose ${ args[0] } failed (attempt ${ attempt } of ${ attempts }). Retrying in ${ delay } seconds...\n` );

			// Sleep synchronously so the retry loop stays in order without going async.
			Atomics.wait( new Int32Array( new SharedArrayBuffer( 4 ) ), 0, 0, delay * 1000 );
		}

		return returns;
	},

	/**
	 * Determines which Docker compose files are required to properly configure the local environment given the
	 * specified PHP version, database type, and database version.
	 *
	 * By default, only the standard docker-compose.yml file will be used.
	 *
	 * @return {string[]} Compose files.
	 */
	get_compose_files: function() {
		const composeFiles = [ 'docker-compose.yml' ];

		if ( existsSync( 'docker-compose.override.yml' ) ) {
			composeFiles.push( 'docker-compose.override.yml' );
		}

		return composeFiles;
	}
};

module.exports = local_env_utils;
