<?php

/**
 * Tests for the filtering of the `default_role` option.
 *
 * @group option
 * @group user
 *
 * @covers ::filter_default_role
 */
class Tests_Functions_FilterDefaultRole extends WP_UnitTestCase {

	/**
	 * Opens or closes user registration.
	 */
	private function set_users_can_register( bool $can_register ) {
		if ( is_multisite() ) {
			update_site_option( 'registration', $can_register ? 'user' : 'none' );
		} else {
			update_option( 'users_can_register', $can_register ? 1 : 0 );
		}
	}

	/**
	 * Ensures privileged roles are left alone when user registration is closed.
	 *
	 * @dataProvider data_excluded_roles
	 */
	public function test_excluded_role_is_unchanged_when_registration_is_closed( string $role ) {
		$this->set_users_can_register( false );

		$this->assertSame(
			$role,
			filter_default_role( $role ),
			'The default role was changed while user registration was closed.'
		);
	}

	/**
	 * Ensures privileged roles are replaced with the subscriber role when user registration is open.
	 *
	 * @dataProvider data_excluded_roles
	 */
	public function test_excluded_role_is_replaced_when_registration_is_open( string $role ) {
		$this->set_users_can_register( true );

		$this->assertSame(
			'subscriber',
			filter_default_role( $role ),
			'The privileged default role was not replaced with the subscriber role.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_excluded_roles() {
		return array(
			'administrator' => array( 'administrator' ),
			'editor'        => array( 'editor' ),
		);
	}

	/**
	 * Ensures roles that are not excluded are left alone when user registration is open.
	 *
	 * @dataProvider data_allowed_roles
	 */
	public function test_allowed_role_is_unchanged_when_registration_is_open( string $role ) {
		$this->set_users_can_register( true );

		$this->assertSame(
			$role,
			filter_default_role( $role ),
			'A role which is not excluded was changed.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_allowed_roles() {
		return array(
			'author'       => array( 'author' ),
			'contributor'  => array( 'contributor' ),
			'subscriber'   => array( 'subscriber' ),
			'unknown role' => array( 'this-role-does-not-exist' ),
			'empty string' => array( '' ),
		);
	}

	/**
	 * Ensures a role can be added to the list of excluded roles.
	 */
	public function test_excluded_roles_can_be_added_to() {
		$this->set_users_can_register( true );

		add_filter(
			'default_role_excluded_roles',
			static function ( $excluded ) {
				$excluded[] = 'author';
				return $excluded;
			}
		);

		$this->assertSame(
			'subscriber',
			filter_default_role( 'author' ),
			'A role added to the excluded roles was not replaced with the subscriber role.'
		);
	}

	/**
	 * Ensures a role can be removed from the list of excluded roles.
	 */
	public function test_excluded_roles_can_be_removed() {
		$this->set_users_can_register( true );

		add_filter( 'default_role_excluded_roles', '__return_empty_array' );

		$this->assertSame(
			'administrator',
			filter_default_role( 'administrator' ),
			'A role removed from the excluded roles was replaced.'
		);
	}

	/**
	 * Ensures a new user is not assigned a privileged role when user registration is open.
	 */
	public function test_new_user_is_not_assigned_a_privileged_role() {
		$this->set_users_can_register( true );
		update_option( 'default_role', 'administrator' );

		$user_id = wp_insert_user(
			array(
				'user_login' => 'test_default_role',
				'user_pass'  => 'password',
				'user_email' => 'test_default_role@example.org',
			)
		);

		$this->assertNotWPError( $user_id, 'The user was not created.' );
		$this->assertSame(
			array( 'subscriber' ),
			get_userdata( $user_id )->roles,
			'The new user was assigned a privileged role.'
		);
	}

	/**
	 * Ensures the network registration setting governs the default role on Multisite, even when
	 * the `users_can_register` option of the current site is closed.
	 *
	 * @ticket 46744
	 * @group ms-required
	 */
	public function test_network_registration_governs_when_site_option_is_closed() {
		update_site_option( 'registration', 'user' );
		update_option( 'users_can_register', 0 );

		$this->assertSame(
			'subscriber',
			filter_default_role( 'administrator' ),
			'The privileged default role was not replaced while network registration was open.'
		);
	}

	/**
	 * Ensures the network registration setting governs the default role on Multisite, even when
	 * the `users_can_register` option of the current site is open.
	 *
	 * @ticket 46744
	 * @group ms-required
	 */
	public function test_network_registration_governs_when_site_option_is_open() {
		update_site_option( 'registration', 'none' );
		update_option( 'users_can_register', 1 );

		$this->assertSame(
			'administrator',
			filter_default_role( 'administrator' ),
			'The default role was changed while network registration was closed.'
		);
	}

	/**
	 * Ensures a new user is assigned the stored role when user registration is closed.
	 */
	public function test_new_user_is_assigned_the_stored_role_when_registration_is_closed() {
		$this->set_users_can_register( false );
		update_option( 'default_role', 'editor' );

		$user_id = wp_insert_user(
			array(
				'user_login' => 'test_default_role',
				'user_pass'  => 'password',
				'user_email' => 'test_default_role@example.org',
			)
		);

		$this->assertNotWPError( $user_id, 'The user was not created.' );
		$this->assertSame(
			array( 'editor' ),
			get_userdata( $user_id )->roles,
			'The new user was not assigned the stored default role.'
		);
	}
}
