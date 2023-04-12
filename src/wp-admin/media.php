<?php
/**
 * Media management action handler.
 *
 * This file is deprecated, use 'wp-admin/upload.php' instead.
 *
 * @deprecated 6.3.0
 * @package WordPress
 * @subpackage Administration
 */

/** Load WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

$parent_file  = 'upload.php';
$submenu_file = 'upload.php';

wp_reset_vars( array( 'action' ) );

switch ( $action ) {
	case 'editattachment':
	case 'edit':
		// Used in the HTML title tag.
		$title = __( 'Edit Media' );

		if ( empty( $errors ) ) {
			$errors = null;
		}

		if ( empty( $_GET['attachment_id'] ) ) {
			wp_redirect( admin_url( 'upload.php' ) );
			exit;
		}
		$att_id = (int) $_GET['attachment_id'];

		wp_redirect( admin_url( "upload.php?item={$att_id}&error=throw-depreciated-media.php" ) );
		exit;

	default:
		wp_redirect( admin_url( 'upload.php?error=throw-depreciated-media.php' ) );
		exit;
}
