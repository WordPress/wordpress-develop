<?php
/**
 * A helper function to lookup "env_FILE", "env", then fallback
 *
 * @param string          $env     The environment variable name.
 * @param string|int|bool $default The default value to use if no value found.
 *
 * @return string|int|bool
 */
function getenv_docker( $env, $default ) {
	// phpcs:disable Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
	if ( $fileEnv = getenv( $env . '_FILE' ) ) {
		return rtrim( strval( file_get_contents( $fileEnv ) ), '\r\n' );
	}
	if ( ( $val = getenv( $env ) ) !== false ) {
		return $val;
	}
	// phpcs:enable

	return $default;
}

/* Path to the WordPress codebase you'd like to test. Add a forward slash in the end. */
define( 'ABSPATH', __DIR__ . '/src/' );

/*
 * Path to the theme to test with.
 *
 * The 'default' theme is symlinked from test/phpunit/data/themedir1/default into
 * the themes directory of the WordPress installation defined above.
 */
define( 'WP_DEFAULT_THEME', 'default' );

/*
 * Test with multisite enabled.
 * Alternatively, use the tests/phpunit/multisite.xml configuration file.
 */
// define( 'WP_TESTS_MULTISITE', true );

/*
 * Force known bugs to be run.
 * Tests with an associated Trac ticket that is still open are normally skipped.
 */
// define( 'WP_TESTS_FORCE_KNOWN_BUGS', true );

// Test with WordPress debug mode (default).
define( 'WP_DEBUG', true );

// ** Database settings ** //

/*
 * This configuration file will be used by the copy of WordPress being tested.
 * wordpress/wp-config.php will be ignored.
 *
 * WARNING WARNING WARNING!
 * These tests will DROP ALL TABLES in the database with the prefix named below.
 * DO NOT use a production database or one that is shared with something else.
 */

/** The name of the database for WordPress */
define( 'DB_NAME', getenv_docker( 'WORDPRESS_TEST_DB_NAME', 'wordpress_develop_tests' ) );

/** Database username */
define( 'DB_USER', getenv_docker( 'WORDPRESS_DB_USER', 'root' ) );

/** Database password */
define( 'DB_PASSWORD', getenv_docker( 'WORDPRESS_DB_PASSWORD', '' ) );

/** Database hostname */
define( 'DB_HOST', getenv_docker( 'WORDPRESS_DB_HOST', 'mysql' ) );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', getenv_docker( 'WORDPRESS_DB_CHARSET', 'utf8' ) );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', getenv_docker( 'WORDPRESS_DB_COLLATE', '' ) );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 */
define( 'AUTH_KEY',         'put your unique phrase here' );
define( 'SECURE_AUTH_KEY',  'put your unique phrase here' );
define( 'LOGGED_IN_KEY',    'put your unique phrase here' );
define( 'NONCE_KEY',        'put your unique phrase here' );
define( 'AUTH_SALT',        'put your unique phrase here' );
define( 'SECURE_AUTH_SALT', 'put your unique phrase here' );
define( 'LOGGED_IN_SALT',   'put your unique phrase here' );
define( 'NONCE_SALT',       'put your unique phrase here' );

$table_prefix = getenv_docker( 'WORDPRESS_TABLE_PREFIX', 'wptests_' );   // Only numbers, letters, and underscores please!

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );

define( 'WPLANG', '' );
