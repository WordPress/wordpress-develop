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

require_once dirname( dirname( __FILE__ ) ) . '/index.php';
