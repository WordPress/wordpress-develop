<?php
/**
 * Code which breaks the security invariants on purpose, standing in for WordPress code.
 *
 * With WP_INVARIANTS_CANARIES=1, gameshark.php counts this directory as code
 * under test, so that the invariants report what it does.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * Prints the search the request sent, escaped or not.
 *
 * @param bool $escape Whether to escape it.
 */
function wp_invariants_canary_print_search( $escape ) {
	$search = isset( $_GET['s'] ) ? wp_unslash( $_GET['s'] ) : ''; // phpcs:ignore WordPress.Security
	echo '<p>' . ( $escape ? esc_html( $search ) : $search ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The canary.
}

/**
 * Looks up posts by the title the request sent, escaped or not.
 *
 * @param bool $escape Whether to escape it.
 */
function wp_invariants_canary_query_title( $escape ) {
	global $wpdb;

	$title = isset( $_GET['t'] ) ? wp_unslash( $_GET['t'] ) : ''; // phpcs:ignore WordPress.Security
	$title = $escape ? esc_sql( $title ) : $title;
	$wpdb->get_results( "SELECT ID FROM {$wpdb->posts} WHERE post_title = '{$title}'" ); // phpcs:ignore WordPress.DB
}

/**
 * Handles an AJAX request by changing an option, after checking a nonce and a capability or not.
 *
 * @param bool $check Whether to check them.
 */
function wp_invariants_canary_change_option( $check ) {
	if ( $check && ( ! check_ajax_referer( 'canary', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) ) {
		return;
	}

	update_option( 'wp_invariants_canary', 'changed' );
}

/**
 * Writes a file into the uploads directory.
 *
 * @param string $name     File name.
 * @param string $contents Contents.
 * @return string The path written.
 */
function wp_invariants_canary_write_upload( $name, $contents ) {
	$path = wp_upload_dir()['basedir'] . '/' . $name;
	file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return $path;
}

/**
 * Writes a file while an archive is extracted, as a filter of pre_unzip_file can.
 *
 * @param string $path Path to write.
 * @return true To stop the extraction there.
 */
function wp_invariants_canary_write_during_unzip( $path ) {
	file_put_contents( $path, 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return true;
}

/**
 * Unserializes data.
 *
 * @param string $data Serialized data.
 * @return mixed
 */
function wp_invariants_canary_unserialize( $data ) {
	return unserialize( $data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- The canary.
}

/**
 * Stores post content straight into the database, past the kses filters.
 *
 * @param string $content Post content.
 */
function wp_invariants_canary_store_content( $content ) {
	global $wpdb;

	$wpdb->insert(
		$wpdb->posts,
		array(
			'post_content' => $content,
			'post_title'   => 'canary',
			'post_type'    => 'post',
		)
	);
}

/**
 * Picks a random number, as a filter of random_password could.
 *
 * @param bool   $secure   Whether to use a secure generator.
 * @param string $password The password, which is returned as it is.
 * @return string
 */
function wp_invariants_canary_random( $secure, $password ) {
	$secure ? random_int( 0, 9 ) : mt_rand( 0, 9 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return $password;
}

/**
 * Sends an email with a subject.
 *
 * @param string $subject Subject.
 */
function wp_invariants_canary_mail( $subject ) {
	wp_mail( 'someone@example.org', $subject, 'Hello.' );
}

/**
 * Returns a user's password hash in a REST response, or not.
 *
 * @param bool $leak Whether to leak it.
 * @return array
 */
function wp_invariants_canary_rest_user( $leak ) {
	$user = get_userdata( (int) get_option( 'wp_invariants_canary_user' ) );
	return $leak ? array( 'hash' => $user->user_pass ) : array( 'id' => $user->ID );
}

/**
 * Changes an option from a public REST route.
 *
 * @return array
 */
function wp_invariants_canary_rest_change() {
	update_option( 'wp_invariants_canary', 'changed' );
	return array( 'changed' => true );
}
