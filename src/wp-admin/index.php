<?php

/**
 * Note: this file exists only to remind developers to build the assets.
 * For the real wp-admin/index.php that gets built and boots WordPress,
 * please refer to wp-admin/_index.php.
 */

if ( file_exists( dirname( __FILE__ ) . '/../wp-includes/js/dist/edit-post.js' ) ) {
	require_once dirname( __FILE__ ) . '/_index.php';
	return;
}

/** Define ABSPATH as this file's directory */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/../' );
}

define( 'WPINC', 'wp-includes' );
require_once( ABSPATH . WPINC . '/load.php' );

// Standardize $_SERVER variables across setups.
wp_fix_server_vars();

require_once( ABSPATH . WPINC . '/functions.php' );
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
require_once( ABSPATH . WPINC . '/version.php' );

wp_check_php_mysql_versions();
wp_load_translations_early();

// Die with an error message
$die = sprintf(
	/* translators: %1$s: WordPress */
	__( 'You are running %1$s without JavaScript and CSS files. These need to be built.' ),
	'WordPress'
) . '</p>';

$die .= '<p>' . __( 'Before running any grunt tasks you need to make sure the dependencies are installed. You can install these by running ' );
$die .= '<code style="color: green;">npm install</code>.</p>';

$die .= '<ul>';
$die .= '<li>' . sprintf(
	/* translators: %s: WordPress */
		__( 'To build %s while developing run:' ),
	'WordPress'
) . '<br /><br />';
$die .= '<code style="color: green;">grunt build --dev</code></li>';
$die .= '<li>' . sprintf(
	__( 'To build files automatically when changing the source files run:' ),
	'WordPress'
) . '<br /><br />';
$die .= '<code style="color: green;">grunt watch</code></li>';
$die .= '<li>' . sprintf(
	__( 'To create a production build of %s run:' ),
	'WordPress'
) . '<br /><br />';
$die .= '<code style="color: green;">grunt build</code></li>';
$die .= '</ul>';


$die .= '<p>' . sprintf(
	/* translators: %1$s: NPM URL, %2$s: Grunt URL */
		__( 'This requires <a href="%1$s">NPM</a> and <a href="%2$s">Grunt</a>. <a href="%3$s">Read more about setting up your local development environment</a>.' ),
	'https://www.npmjs.com/',
	'https://gruntjs.com/',
	__( 'https://make.wordpress.org/core/handbook/tutorials/installing-wordpress-locally/' )
) . '</p>';

wp_die( $die, __( 'WordPress &rsaquo; Error' ) );
