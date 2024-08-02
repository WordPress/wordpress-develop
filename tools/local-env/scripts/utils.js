const local_env_utils = {

	/**
	 * Determines which Docker compose files are required to properly configure the local environment given the
	 * specified PHP version, database type, and database version.
	 */
	determine_compose_files: function() {
		process.env.LOCAL_COMPOSE_FILE = '-f docker-compose.yml';

		if ( process.env.LOCAL_DB_TYPE !== 'mysql' ) {
			return;
		}

		if ( process.env.LOCAL_PHP !== '7.2-fpm' && process.env.LOCAL_PHP !== '7.3-fpm' ) {
			return;
		}

		// PHP 7.2/7.3 in combination with MySQL 8.4 requires additional configuration to function properly.
		if ( process.env.LOCAL_DB_VERSION === '8.4' ) {
			process.env.LOCAL_COMPOSE_FILE = process.env.LOCAL_COMPOSE_FILE + ' -f docker-compose.old-php-mysql-84.override.yml';
		}
	},

	/**
	 * Determines the option to pass for proper authentication plugin configuration given the specified PHP version,
	 * database type, and database version.
	 *
	 * Unless a specific
	 */
	determine_auth_option: function() {
		process.env.LOCAL_DB_AUTH_OPTION = '';

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
