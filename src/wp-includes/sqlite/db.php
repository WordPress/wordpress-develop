<?php
/**
 * Main integration file.
 *
 * @package wp-sqlite-integration
 * @since 1.0.0
 */

// Bail early if DB_ENGINE is not defined as sqlite.
if ( ! defined( 'DB_ENGINE' ) || 'sqlite' !== DB_ENGINE ) {
	return;
}

/**
 * FQDBDIR is a directory where the sqlite database file is placed.
 * If DB_DIR is defined, it is used as FQDBDIR.
 */
if ( ! defined( 'FQDBDIR' ) ) {
	if ( defined( 'DB_DIR' ) ) {
		define( 'FQDBDIR', trailingslashit( DB_DIR ) );
	} elseif ( defined( 'WP_CONTENT_DIR' ) ) {
		define( 'FQDBDIR', WP_CONTENT_DIR . '/database/' );
	} else {
		define( 'FQDBDIR', ABSPATH . 'wp-content/database/' );
	}
}

/**
 * FQDB is a database file name. If DB_FILE is defined, it is used
 * as FQDB.
 */
if ( ! defined( 'FQDB' ) ) {
	if ( defined( 'DB_FILE' ) ) {
		define( 'FQDB', FQDBDIR . DB_FILE );
	} else {
		define( 'FQDB', FQDBDIR . '.ht.sqlite' );
	}
}


if ( ! extension_loaded( 'pdo' ) ) {
	wp_die(
		new WP_Error(
			'pdo_not_loaded',
			sprintf(
				'<h1>%1$s</h1><p>%2$s</p>',
				'PHP PDO Extension is not loaded',
				'Your PHP installation appears to be missing the PDO extension which is required for this version of WordPress and the type of database you have specified.'
			)
		),
		'PHP PDO Extension is not loaded.'
	);
}

if ( ! extension_loaded( 'pdo_sqlite' ) ) {
	wp_die(
		new WP_Error(
			'pdo_driver_not_loaded',
			sprintf(
				'<h1>%1$s</h1><p>%2$s</p>',
				'PDO Driver for SQLite is missing',
				'Your PHP installation appears not to have the right PDO drivers loaded. These are required for this version of WordPress and the type of database you have specified.'
			)
		),
		'PDO Driver for SQLite is missing.'
	);
}

require_once __DIR__ . '/class-wp-sqlite-lexer.php';
require_once __DIR__ . '/class-wp-sqlite-query-rewriter.php';
require_once __DIR__ . '/class-wp-sqlite-translator.php';
require_once __DIR__ . '/class-wp-sqlite-token.php';
require_once __DIR__ . '/class-wp-sqlite-pdo-user-defined-functions.php';
require_once __DIR__ . '/class-wp-sqlite-db.php';

$GLOBALS['wpdb'] = new WP_SQLite_DB();
