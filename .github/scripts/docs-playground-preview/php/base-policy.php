<?php
/**
 * Build-time policy for the Code Reference invariant base.
 *
 * Final runtime restrictions are installed only after the reference import.
 */

if ( ! defined( 'WPORG_DEVELOPER_PREVIEW' ) ) {
	define( 'WPORG_DEVELOPER_PREVIEW', true );
}

if ( ! defined( 'WP_ENVIRONMENT_TYPE' ) ) {
	define( 'WP_ENVIRONMENT_TYPE', 'local' );
}

if ( ! defined( 'FEATURE_2021_GLOBAL_HEADER_FOOTER' ) ) {
	define( 'FEATURE_2021_GLOBAL_HEADER_FOOTER', true );
}

add_filter(
	'pre_option_blog_public',
	static function () {
		return '0';
	}
);

add_filter( 'wp_is_application_passwords_available', '__return_false' );

foreach ( array( 'update_core', 'update_plugins', 'update_themes' ) as $transient ) {
	add_filter(
		"pre_site_transient_{$transient}",
		static function () {
			return (object) array(
				'last_checked' => time(),
				'checked'      => array(),
				'no_update'    => array(),
				'response'     => array(),
				'translations' => array(),
				'updates'      => array(),
			);
		}
	);
}
