<?php
/**
 * Shared fixture for Secrets API tests that store, delete, or retire secrets.
 *
 * @package WordPress
 * @subpackage Secrets
 */

/**
 * Provides a current user who is allowed to manage secrets.
 *
 * Storing, importing, deleting, and retiring a secret all require the `manage_secret`
 * or `manage_network_secret` capability, so a test that is about anything other than
 * that check runs as a user who has both.
 */
trait WP_Secrets_Manager_User {

	/**
	 * Sets the current user to one who can manage site-scope and network-scope secrets.
	 *
	 * An administrator can manage both on a single site. On Multisite, managing
	 * network-scope secrets takes a super admin.
	 *
	 * @return int The user ID.
	 */
	protected function set_current_user_to_secrets_manager(): int {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		if ( is_multisite() ) {
			grant_super_admin( $user_id );
		}

		wp_set_current_user( $user_id );

		return $user_id;
	}
}
