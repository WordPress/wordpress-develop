/* jshint node:true */

const { existsSync } = require( 'node:fs' );

const local_env_utils = {

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
