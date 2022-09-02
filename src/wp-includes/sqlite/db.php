<?php
/**
 * Plugin Name: WP SQLite DB
 * Description: SQLite database driver drop-in. (based on SQLite Integration by Kojima Toshiyasu)
 * Author: Evan Mattson
 * Author URI: https://aaemnnost.tv
 * Plugin URI: https://github.com/aaemnnosttv/wp-sqlite-db
 * Version: 1.2.0
 * Requires PHP: 5.6
 *
 * This file must be placed in wp-content/db.php.
 * WordPress loads this file automatically.
 *
 * This project is based on the original work of Kojima Toshiyasu and his SQLite Integration plugin.
 */

function pdo_log_error( $message, $data = null ) {
	$admin_dir = 'wp-admin/';
	if ( strpos( $_SERVER['SCRIPT_NAME'], 'wp-admin' ) !== false ) {
		$admin_dir = '';
	}
	die(
		<<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>WordPress &rsaquo; Error</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<link rel="stylesheet" href="{$admin_dir}css/install.css" type="text/css" />
</head>
<body>
<h1 id="logo"><img alt="WordPress" src="{$admin_dir}images/wordpress-logo.png" /></h1>
<p>$message</p>
<p>$data</p>
</body>
<html>

HTML
	);
}

if ( ! extension_loaded( 'pdo' ) ) {
	pdo_log_error(
		'PHP PDO Extension is not loaded.',
		'Your PHP installation appears to be missing the PDO extension which is required for this version of WordPress.'
	);
}

if ( ! extension_loaded( 'pdo_sqlite' ) ) {
	pdo_log_error(
		'PDO Driver for SQLite is missing.',
		'Your PHP installation appears not to have the right PDO drivers loaded. These are required for this version of WordPress and the type of database you have specified.'
	);
}

/**
 * Notice:
 * Your scripts have the permission to create directories or files on your server.
 * If you write in your wp-config.php like below, we take these definitions.
 * define('DB_DIR', '/full_path_to_the_database_directory/');
 * define('DB_FILE', 'database_file_name');
 */

/**
 * FQDBDIR is a directory where the sqlite database file is placed.
 * If DB_DIR is defined, it is used as FQDBDIR.
 */
if ( defined( 'DB_DIR' ) ) {
	define( 'FQDBDIR', trailingslashit( DB_DIR ) );
} elseif ( defined( 'WP_CONTENT_DIR' ) ) {
	define( 'FQDBDIR', WP_CONTENT_DIR . '/database/' );
} else {
	define( 'FQDBDIR', ABSPATH . 'wp-content/database/' );
}

/**
 * FQDB is a database file name. If DB_FILE is defined, it is used
 * as FQDB.
 */
if ( defined( 'DB_FILE' ) ) {
	define( 'FQDB', FQDBDIR . DB_FILE );
} else {
	define( 'FQDB', FQDBDIR . '.ht.sqlite' );
}

require_once ABSPATH . WPINC . '/sqlite/class-wp-pdo-sqlite-user-defined-functions.php';
require_once ABSPATH . WPINC . '/sqlite/class-wp-pdo-engine.php';
require_once ABSPATH . WPINC . '/sqlite/class-wp-sqlite-object-array.php';
require_once ABSPATH . WPINC . '/sqlite/class-wp-sqlite-db.php';
require_once ABSPATH . WPINC . '/sqlite/class-wp-pdo-sqlite-driver.php';
require_once ABSPATH . WPINC . '/sqlite/class-wp-sqlite-create-query.php';
require_once ABSPATH . WPINC . '/sqlite/class-wp-sqlite-alter-query.php';

/**
 * Function to create tables according to the schemas of WordPress.
 *
 * This is executed only once while installation.
 *
 * @return boolean
 */
function make_db_sqlite() {
	include_once ABSPATH . 'wp-admin/includes/schema.php';
	$index_array = array();

	$table_schemas = wp_get_db_schema();
	$queries       = explode( ';', $table_schemas );
	$query_parser  = new WP_SQLite_Create_Query();
	try {
		$pdo = new PDO( 'sqlite:' . FQDB, null, null, array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ) );
	} catch ( PDOException $err ) {
		$err_data = $err->errorInfo; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$message  = 'Database connection error!<br />';
		$message .= sprintf( 'Error message is: %s', $err_data[2] );
		wp_die( $message, 'Database Error!' );
	}

	try {
		$pdo->beginTransaction();
		foreach ( $queries as $query ) {
			$query = trim( $query );
			if ( empty( $query ) ) {
				continue;
			}
			$rewritten_query = $query_parser->rewrite_query( $query );
			if ( is_array( $rewritten_query ) ) {
				$table_query   = array_shift( $rewritten_query );
				$index_queries = $rewritten_query;
				$table_query   = trim( $table_query );
				$pdo->exec( $table_query );
				//foreach($rewritten_query as $single_query) {
				//  $single_query = trim($single_query);
				//  $pdo->exec($single_query);
				//}
			} else {
				$rewritten_query = trim( $rewritten_query );
				$pdo->exec( $rewritten_query );
			}
		}
		$pdo->commit();
		if ( $index_queries ) {
			// $query_parser rewrites KEY to INDEX, so we don't need KEY pattern
			$pattern = '/CREATE\\s*(UNIQUE\\s*INDEX|INDEX)\\s*IF\\s*NOT\\s*EXISTS\\s*(\\w+)?\\s*.*/im';
			$pdo->beginTransaction();
			foreach ( $index_queries as $index_query ) {
				preg_match( $pattern, $index_query, $match );
				$index_name = trim( $match[2] );
				if ( in_array( $index_name, $index_array ) ) {
					$r           = rand( 0, 50 );
					$replacement = $index_name . "_$r";
					$index_query = str_ireplace(
						'EXISTS ' . $index_name,
						'EXISTS ' . $replacement,
						$index_query
					);
				} else {
					$index_array[] = $index_name;
				}
				$pdo->exec( $index_query );
			}
			$pdo->commit();
		}
	} catch ( PDOException $err ) {
		$err_data = $err->errorInfo; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$err_code = $err_data[1];
		if ( 5 == $err_code || 6 == $err_code ) {
			// if the database is locked, commit again
			$pdo->commit();
		} else {
			$pdo->rollBack();
			$message  = sprintf(
				'Error occured while creating tables or indexes...<br />Query was: %s<br />',
				var_export( $rewritten_query, true )
			);
			$message .= sprintf( 'Error message is: %s', $err_data[2] );
			wp_die( $message, 'Database Error!' );
		}
	}

	$query_parser = null;
	$pdo          = null;

	return true;
}

/**
 * Installs the site.
 *
 * Runs the required functions to set up and populate the database,
 * including primary admin user and initial options.
 *
 * @since 2.1.0
 *
 * @param string $blog_title Site title.
 * @param string $user_name User's username.
 * @param string $user_email User's email.
 * @param bool $public Whether site is public.
 * @param string $deprecated Optional. Not used.
 * @param string $user_password Optional. User's chosen password. Default empty (random password).
 * @param string $language Optional. Language chosen. Default empty.
 *
 * @return array Array keys 'url', 'user_id', 'password', and 'password_message'.
 */
function wp_install( $blog_title, $user_name, $user_email, $public, $deprecated = '', $user_password = '', $language = '' ) {
	if ( ! empty( $deprecated ) ) {
		_deprecated_argument( __FUNCTION__, '2.6.0' );
	}

	wp_check_mysql_version();
	wp_cache_flush();
	/* begin wp-sqlite-db changes */
	// make_db_current_silent();
	make_db_sqlite();
	/* end wp-sqlite-db changes */
	populate_options();
	populate_roles();

	update_option( 'blogname', $blog_title );
	update_option( 'admin_email', $user_email );
	update_option( 'blog_public', $public );

	// Freshness of site - in the future, this could get more specific about actions taken, perhaps.
	update_option( 'fresh_site', 1 );

	if ( $language ) {
		update_option( 'WPLANG', $language );
	}

	$guessurl = wp_guess_url();

	update_option( 'siteurl', $guessurl );

	// If not a public blog, don't ping.
	if ( ! $public ) {
		update_option( 'default_pingback_flag', 0 );
	}

	/*
		* Create default user. If the user already exists, the user tables are
		* being shared among sites. Just set the role in that case.
		*/
	$user_id        = username_exists( $user_name );
	$user_password  = trim( $user_password );
	$email_password = false;
	if ( ! $user_id && empty( $user_password ) ) {
		$user_password = wp_generate_password( 12, false );
		$message       = __( '<strong><em>Note that password</em></strong> carefully! It is a <em>random</em> password that was generated just for you.' );
		$user_id       = wp_create_user( $user_name, $user_password, $user_email );
		update_user_option( $user_id, 'default_password_nag', true, true );
		$email_password = true;
	} elseif ( ! $user_id ) {
		// Password has been provided
		$message = '<em>' . __( 'Your chosen password.' ) . '</em>';
		$user_id = wp_create_user( $user_name, $user_password, $user_email );
	} else {
		$message = __( 'User already exists. Password inherited.' );
	}

	$user = new WP_User( $user_id );
	$user->set_role( 'administrator' );

	wp_install_defaults( $user_id );

	wp_install_maybe_enable_pretty_permalinks();

	flush_rewrite_rules();

	wp_new_blog_notification( $blog_title, $guessurl, $user_id, ( $email_password ? $user_password : __( 'The password you chose during installation.' ) ) );

	wp_cache_flush();

	/**
	 * Fires after a site is fully installed.
	 *
	 * @since 3.9.0
	 *
	 * @param WP_User $user The site owner.
	 */
	do_action( 'wp_install', $user );

	return array(
		'url'              => $guessurl,
		'user_id'          => $user_id,
		'password'         => $user_password,
		'password_message' => $message,
	);
}

$GLOBALS['wpdb'] = new WP_SQLite_DB();
