<?php
/**
 * XML-RPC Multisite tests.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 7.2.0
 */

/**
 * Tests for wp_xmlrpc_server::wp_getUsers() in Multisite.
 *
 * @group xmlrpc
 * @group user
 * @group ms-required
 */
class Tests_XMLRPC_Wp_GetUsers_Multisite extends WP_XMLRPC_UnitTestCase {
	/**
	 * Ensures that a regular site administrator in Multisite receives users
	 * with only the basic fields, not the full set of user data.
	 *
	 * @ticket 25016
	 */
	public function test_site_administrator_receives_only_basic_fields() {
		$administrator_id = $this->make_user_by_role( 'administrator' );
		$this->make_user_by_role( 'editor' );
		$this->make_user_by_role( 'author' );

		$this->assertFalse( is_super_admin( $administrator_id ), 'The admin user should not be a super admin.' );
		$results = $this->myxmlrpcserver->wp_getUsers( array( 1, 'administrator', 'administrator' ) );

		// The administrator should see all users on the site (at least 3).
		$this->assertGreaterThanOrEqual( 3, count( $results ) );

		// Fields that should be present for a regular site administrator.
		$basic_fields = array(
			'user_id',
			'username',
			'email',
			'registered',
			'display_name',
			'nicename',
		);

		foreach ( $results as $user ) {
			// All basic fields should be present.
			foreach ( $basic_fields as $field ) {
				$this->assertArrayHasKey( $field, $user, "Field '{$field}' should be present." );
			}
			// No field outside the basic list should be present.
			$returned_fields = array_keys( $user );
			$extra_fields    = array_diff( $returned_fields, $basic_fields );
			$this->assertEmpty(
				$extra_fields,
				'Unexpected fields returned for a site administrator: ' . implode( ', ', $extra_fields )
			);
		}
	}
}
