<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * This has been slightly modified (to read environment variables) for use in Docker.
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

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

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', getenv_docker( 'WORDPRESS_DB_NAME', 'wordpress_develop' ) );

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
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'put your unique phrase here' );
define( 'SECURE_AUTH_KEY',  'put your unique phrase here' );
define( 'LOGGED_IN_KEY',    'put your unique phrase here' );
define( 'NONCE_KEY',        'put your unique phrase here' );
define( 'AUTH_SALT',        'put your unique phrase here' );
define( 'SECURE_AUTH_SALT', 'put your unique phrase here' );
define( 'LOGGED_IN_SALT',   'put your unique phrase here' );
define( 'NONCE_SALT',       'put your unique phrase here' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = getenv_docker( 'WORDPRESS_TABLE_PREFIX', 'wp_' );

// Configure site domain name for Codespaces if present.
$is_codespaces = boolval( getenv( 'CODESPACES' ) );
$codespace_name = getenv_docker( 'CODESPACE_NAME', '' );
$codespace_domain = getenv_docker( 'GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN', '' );
if ( $is_codespaces ) {
	$site_domain = $codespace_name . '-8080.' . $codespace_domain;
	define( 'WP_HOME', 'https://' . $codespace_name . '-8080.' . $codespace_domain );
} else {
	$site_domain = 'localhost';
	define( 'WP_HOME', 'http://localhost:8080' );
}

defined( 'WP_SITEURL' ) || define( 'WP_SITEURL', rtrim( WP_HOME, '/' ) );

// If we're behind a proxy server and using HTTPS, we need to alert WordPress of that fact
// see also http://codex.wordpress.org/Administration_Over_SSL#Using_a_Reverse_Proxy
if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] ) {
	$_SERVER['HTTPS'] = 'on';
	$_SERVER['HTTP_HOST'] = $site_domain;
}
// (we include this by default because reverse proxying is extremely common in container environments)
// phpcs:disable Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
if ( $configExtra = getenv_docker( 'WORDPRESS_CONFIG_EXTRA', '' ) ) {
  // phpcs:disable Squiz.PHP.Eval.Discouraged
	eval( $configExtra );
}
// phpcs:enable

define( 'WP_LOCAL_DEV', true );
defined( 'WP_ENVIRONMENT_TYPE' ) || define( 'WP_ENVIRONMENT_TYPE', 'development' );

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', true );
define( 'SCRIPT_DEBUG', true );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/' );

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
