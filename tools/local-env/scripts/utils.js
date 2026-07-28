/* jshint node:true */

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
