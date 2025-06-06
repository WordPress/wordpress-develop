<?php
/**
 * Loads the old Requests class file when the autoloader
 * references the original PSR-0 Requests class.
 *
 * @deprecated 6.2.0
 * @package WordPress
 * @subpackage Requests
 * @since 6.2.0
 */

// Don't load directly.
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

include_once ABSPATH . WPINC . '/class-requests.php';
