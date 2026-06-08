const { existsSync } = require( 'node:fs' );

const local_env_utils = {

	/**
	 * Determines which Docker compose files are required to properly configure the local environment given the
	 * specified PHP version, database type, and database version.
	 *
	 * By default, only the standard docker-compose.yml file will be used.
	 *
	 * When PHP 7.2 or 7.3 is used in combination with MySQL 8.4, an override file will also be returned to ensure
	 * that the mysql_native_password plugin authentication plugin is on and available for use.
	 */
	get_compose_files: function() {
		var composeFiles = '-f docker-compose.yml';

		if ( existsSync( 'docker-compose.override.yml' ) ) {
			composeFiles = composeFiles + ' -f docker-compose.override.yml';
		}

		if ( process.env.LOCAL_DB_TYPE !== 'mysql' ) {
			return composeFiles;
		}

		if ( process.env.LOCAL_PHP !== '7.2-fpm' && process.env.LOCAL_PHP !== '7.3-fpm' ) {
			return composeFiles;
		}

		// PHP 7.2/7.3 in combination with MySQL 8.4 requires additional configuration to function properly.
		if ( process.env.LOCAL_DB_VERSION === '8.4' ) {
			composeFiles = composeFiles + ' -f tools/local-env/old-php-mysql-84.override.yml';
		}

		return composeFiles;
	},

	/**
	 * Determines the option to pass for proper authentication plugin configuration given the specified PHP version,
	 * database type, and database version.
	 */
	determine_auth_option: function() {
		if ( process.env.LOCAL_DB_TYPE !== 'mysql' ) {
			return;
		}

		if ( process.env.LOCAL_PHP !== '7.2-fpm' && process.env.LOCAL_PHP !== '7.3-fpm' ) {
			return;
		}

		// MySQL 8.4 removed --default-authentication-plugin in favor of --authentication-policy.
		if ( process.env.LOCAL_DB_VERSION === '8.4' ) {
			process.env.LOCAL_DB_AUTH_OPTION = '--authentication-policy=mysql_native_password';
		} else {
			process.env.LOCAL_DB_AUTH_OPTION = '--default-authentication-plugin=mysql_native_password';
		}
	}
};

module.exports = local_env_utils;
