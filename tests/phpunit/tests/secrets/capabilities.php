<?php
/**
 * Tests for the Secrets API's capabilities.
 *
 * `manage_secrets` and `manage_network_secrets` are primitive capabilities granted
 * through the `user_has_cap` filter rather than stored on a role. `manage_secret` and
 * `manage_network_secret` are the meta capabilities for a single secret, and are what
 * the functions that store, import, delete, and retire a secret check.
 *
 * @ticket 66187
 * @ticket 66696
 *
 * @group secrets
 * @group capabilities
 */
class Tests_Secrets_Capabilities extends WP_UnitTestCase {

	use WP_Secrets_Assertions;
	use WP_Secrets_Manager_User;

	/**
	 * Ensure that manage_secrets is not stored on any default role.
	 *
	 * It is granted through the `user_has_cap` filter, as view_site_health_checks is,
	 * so there is nothing for an install or an upgrade to write to the database.
	 *
	 * @covers ::wp_maybe_grant_secrets_caps
	 *
	 * @dataProvider data_default_roles
	 *
	 * @param string $role A default role.
	 */
	public function test_manage_secrets_is_not_stored_on_a_role( $role ): void {
		$role_object = get_role( $role );

		$this->assertInstanceOf( WP_Role::class, $role_object, 'The role should exist.' );
		$this->assertFalse( $role_object->has_cap( 'manage_secrets' ), 'The role should not have manage_secrets stored on it.' );
		$this->assertFalse( $role_object->has_cap( 'manage_network_secrets' ), 'The role should not have manage_network_secrets stored on it.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_default_roles() {
		return array(
			'administrator' => array( 'administrator' ),
			'editor'        => array( 'editor' ),
			'author'        => array( 'author' ),
			'contributor'   => array( 'contributor' ),
			'subscriber'    => array( 'subscriber' ),
		);
	}

	/**
	 * Ensure that only the administrator role can manage secrets by default.
	 *
	 * @covers ::wp_maybe_grant_secrets_caps
	 *
	 * @dataProvider data_which_roles_can_manage_secrets
	 *
	 * @param string $role     A default role.
	 * @param bool   $expected Whether a user with the role should have manage_secrets.
	 */
	public function test_which_roles_can_manage_secrets( $role, $expected ): void {
		$user_id = self::factory()->user->create( array( 'role' => $role ) );

		$this->assertSame( $expected, user_can( $user_id, 'manage_secrets' ), 'Only an administrator should have manage_secrets.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public static function data_which_roles_can_manage_secrets() {
		return array(
			'administrator' => array( 'administrator', true ),
			'editor'        => array( 'editor', false ),
			'author'        => array( 'author', false ),
			'contributor'   => array( 'contributor', false ),
			'subscriber'    => array( 'subscriber', false ),
		);
	}

	/**
	 * Ensure that manage_secrets follows manage_options.
	 *
	 * @covers ::wp_maybe_grant_secrets_caps
	 */
	public function test_manage_secrets_follows_manage_options(): void {
		$user = self::factory()->user->create_and_get( array( 'role' => 'editor' ) );

		$this->assertFalse( user_can( $user, 'manage_secrets' ), 'An editor should not have manage_secrets.' );

		$user->add_cap( 'manage_options' );

		$this->assertTrue( user_can( $user, 'manage_secrets' ), 'A user given manage_options should have manage_secrets.' );
	}

	/**
	 * Ensure that a single site administrator can manage network-scope secrets.
	 *
	 * Network-scope secrets work on a single site, where no role has
	 * manage_network_options, so the capability follows manage_options there.
	 *
	 * @group ms-excluded
	 *
	 * @covers ::wp_maybe_grant_secrets_caps
	 */
	public function test_single_site_administrator_has_manage_network_secrets(): void {
		$this->skipWithMultisite();

		$administrator = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$editor        = self::factory()->user->create( array( 'role' => 'editor' ) );

		$this->assertTrue( user_can( $administrator, 'manage_network_secrets' ), 'A single site administrator should have manage_network_secrets.' );
		$this->assertFalse( user_can( $editor, 'manage_network_secrets' ), 'An editor should not have manage_network_secrets.' );
	}

	/**
	 * Ensure that only super admins have manage_network_secrets on Multisite.
	 *
	 * @group ms-required
	 *
	 * @covers ::wp_maybe_grant_secrets_caps
	 */
	public function test_only_super_admins_have_manage_network_secrets(): void {
		$this->skipWithoutMultisite();

		$administrator = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$super_admin   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $super_admin );

		$this->assertFalse( user_can( $administrator, 'manage_network_secrets' ), 'An administrator who is not a super admin should not have manage_network_secrets.' );
		$this->assertTrue( user_can( $super_admin, 'manage_network_secrets' ), 'A super admin should have manage_network_secrets.' );
	}

	/**
	 * Ensure that manage_network_secrets follows manage_network_options on Multisite.
	 *
	 * @group ms-required
	 *
	 * @covers ::wp_maybe_grant_secrets_caps
	 */
	public function test_manage_network_secrets_follows_manage_network_options(): void {
		$this->skipWithoutMultisite();

		$user = self::factory()->user->create_and_get( array( 'role' => 'administrator' ) );
		$user->add_cap( 'manage_network_options' );

		$this->assertTrue( user_can( $user, 'manage_network_secrets' ), 'A user given manage_network_options should have manage_network_secrets.' );
	}

	/**
	 * Ensure that each meta capability maps to its primitive capability.
	 *
	 * @covers ::map_meta_cap
	 *
	 * @dataProvider data_meta_capabilities
	 *
	 * @param string $meta_cap  The meta capability.
	 * @param string $primitive The primitive capability it should map to.
	 */
	public function test_meta_capability_maps_to_its_primitive( $meta_cap, $primitive ): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$this->assertSame( array( $primitive ), map_meta_cap( $meta_cap, $user_id, 'myplugin/api-key' ), 'The meta capability should map to its primitive capability.' );
	}

	/**
	 * Ensure that a meta capability checked without a secret name is refused.
	 *
	 * @covers ::map_meta_cap
	 *
	 * @expectedIncorrectUsage map_meta_cap
	 *
	 * @dataProvider data_meta_capabilities
	 *
	 * @param string $meta_cap The meta capability.
	 */
	public function test_meta_capability_without_a_secret_name_is_refused( $meta_cap ): void {
		$user_id = $this->set_current_user_to_secrets_manager();

		$this->assertSame( array( 'do_not_allow' ), map_meta_cap( $meta_cap, $user_id ), 'A meta capability checked without a secret name should map to do_not_allow.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function data_meta_capabilities() {
		return array(
			'manage_secret'         => array( 'manage_secret', 'manage_secrets' ),
			'manage_network_secret' => array( 'manage_network_secret', 'manage_network_secrets' ),
		);
	}

	/**
	 * Ensure that every function that changes a secret succeeds for a user who can manage it.
	 *
	 * @covers ::_wp_secrets_check_manage_capability
	 * @covers ::wp_set_secret
	 * @covers ::wp_import_option_as_secret
	 * @covers ::wp_delete_secret
	 * @covers ::wp_retire_secret_version
	 * @covers ::wp_set_network_secret
	 * @covers ::wp_delete_network_secret
	 * @covers ::wp_retire_network_secret_version
	 *
	 * @dataProvider data_functions_that_change_a_secret
	 *
	 * @param callable $change Changes the secret 'myplugin/api-key'.
	 */
	public function test_a_user_who_can_manage_secrets_can_change_one( $change ): void {
		$this->seed_secret();

		$this->assertTrue( $change(), 'A user who can manage secrets should be able to change one.' );
	}

	/**
	 * Ensure that every function that changes a secret refuses a user who cannot manage it.
	 *
	 * Covers a request with no logged-in user, such as cron, and a logged-in user
	 * without the capability. Nothing is changed and wp_secret_changed does not fire.
	 *
	 * @covers ::_wp_secrets_check_manage_capability
	 * @covers ::wp_set_secret
	 * @covers ::wp_import_option_as_secret
	 * @covers ::wp_delete_secret
	 * @covers ::wp_retire_secret_version
	 * @covers ::wp_set_network_secret
	 * @covers ::wp_delete_network_secret
	 * @covers ::wp_retire_network_secret_version
	 *
	 * @dataProvider data_functions_that_change_a_secret_for_users_who_cannot
	 *
	 * @param callable $change  Changes the secret 'myplugin/api-key'.
	 * @param bool     $network Whether the function changes the network-scope secret.
	 * @param string   $role    A role without the capability, or '' for no logged-in user.
	 */
	public function test_a_user_who_cannot_manage_secrets_cannot_change_one( $change, $network, $role ): void {
		$this->seed_secret();

		wp_set_current_user( '' === $role ? 0 : self::factory()->user->create( array( 'role' => $role ) ) );

		$action = new MockAction();
		add_action( 'wp_secret_changed', array( $action, 'action' ) );

		$result = $change();

		$this->assertWPError( $result, 'A user who cannot manage secrets should be refused.' );
		$this->assertSame( WP_SECRETS_ERROR_NOT_ALLOWED, $result->get_error_code(), 'The refusal should use the not allowed error code.' );
		$this->assertSame( 0, $action->get_call_count(), 'A refused change should not fire wp_secret_changed.' );
		$this->assertRecordSlotDecryptsTo( 'myplugin/api-key', WP_Secret_Version::CURRENT, 'second-value', $network, 'A refused change should leave the current value in place.' );
		$this->assertRecordSlotDecryptsTo( 'myplugin/api-key', WP_Secret_Version::PREVIOUS, 'first-value', $network, 'A refused change should leave the previous value in place.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: callable, 1: bool}>
	 */
	public static function data_functions_that_change_a_secret() {
		return array(
			'wp_set_secret()'                    => array(
				static function () {
					return wp_set_secret( 'myplugin/api-key', 'third-value' );
				},
				false,
			),
			'wp_import_option_as_secret()'       => array(
				static function () {
					return wp_import_option_as_secret( 'myplugin_legacy_api_key', 'myplugin/api-key' );
				},
				false,
			),
			'wp_delete_secret()'                 => array(
				static function () {
					return wp_delete_secret( 'myplugin/api-key' );
				},
				false,
			),
			'wp_retire_secret_version()'         => array(
				static function () {
					return wp_retire_secret_version( 'myplugin/api-key' );
				},
				false,
			),
			'wp_set_network_secret()'            => array(
				static function () {
					return wp_set_network_secret( 'myplugin/api-key', 'third-value' );
				},
				true,
			),
			'wp_delete_network_secret()'         => array(
				static function () {
					return wp_delete_network_secret( 'myplugin/api-key' );
				},
				true,
			),
			'wp_retire_network_secret_version()' => array(
				static function () {
					return wp_retire_network_secret_version( 'myplugin/api-key' );
				},
				true,
			),
		);
	}

	/**
	 * Data provider.
	 *
	 * Every function that changes a secret, for a request with no logged-in user and
	 * for a subscriber.
	 *
	 * @return array<string, array{0: callable, 1: bool, 2: string}>
	 */
	public static function data_functions_that_change_a_secret_for_users_who_cannot() {
		$data = array();

		foreach ( self::data_functions_that_change_a_secret() as $function => $arguments ) {
			$data[ "{$function} with no logged-in user" ] = array( $arguments[0], $arguments[1], '' );
			$data[ "{$function} as a subscriber" ]        = array( $arguments[0], $arguments[1], 'subscriber' );
		}

		return $data;
	}

	/**
	 * Ensure that a site administrator cannot change a network-scope secret on Multisite.
	 *
	 * @group ms-required
	 *
	 * @covers ::_wp_secrets_check_manage_capability
	 * @covers ::wp_set_network_secret
	 */
	public function test_a_site_administrator_cannot_change_a_network_secret_on_multisite(): void {
		$this->skipWithoutMultisite();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'A site administrator should be able to store a site-scope secret.' );

		$result = wp_set_network_secret( 'myplugin/api-key', 'value' );

		$this->assertWPError( $result, 'A site administrator should not be able to store a network-scope secret.' );
		$this->assertSame( WP_SECRETS_ERROR_NOT_ALLOWED, $result->get_error_code(), 'The refusal should use the not allowed error code.' );
	}

	/**
	 * Ensure that reading and listing secrets take no capability.
	 *
	 * The code that uses a secret has to be able to read it on any request,
	 * including cron and requests with no logged-in user.
	 *
	 * @covers ::wp_get_secret
	 * @covers ::wp_list_secrets
	 * @covers ::wp_get_network_secret
	 * @covers ::wp_list_network_secrets
	 */
	public function test_reading_and_listing_take_no_capability(): void {
		$this->seed_secret();

		wp_set_current_user( 0 );

		$this->assertIsSecret( wp_get_secret( 'myplugin/api-key' ), 'second-value', 'A site-scope secret should be readable with no logged-in user.' );
		$this->assertIsSecret( wp_get_network_secret( 'myplugin/api-key' ), 'second-value', 'A network-scope secret should be readable with no logged-in user.' );
		$this->assertSame( array( 'myplugin/api-key' ), wp_list_pluck( wp_list_secrets(), 'name' ), 'Site-scope secrets should be listable with no logged-in user.' );
		$this->assertSame( array( 'myplugin/api-key' ), wp_list_pluck( wp_list_network_secrets(), 'name' ), 'Network-scope secrets should be listable with no logged-in user.' );
	}

	/**
	 * Ensure that a plugin can let a specific user manage one of its own secrets.
	 *
	 * The secret's name is passed to the map_meta_cap filter, so a plugin can decide
	 * who manages each of its secrets without widening manage_secrets itself.
	 *
	 * @covers ::_wp_secrets_check_manage_capability
	 * @covers ::map_meta_cap
	 */
	public function test_a_plugin_can_allow_one_secret_to_a_specific_user(): void {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );

		add_filter(
			'map_meta_cap',
			static function ( $caps, $cap, $user_id, $args ) use ( $editor ) {
				if ( 'manage_secret' === $cap && 'myplugin/editor-key' === $args[0] && $editor === $user_id ) {
					return array( 'exist' );
				}

				return $caps;
			},
			10,
			4
		);

		$this->assertTrue( wp_set_secret( 'myplugin/editor-key', 'value' ), 'The user the plugin allowed should be able to store that secret.' );

		$other = wp_set_secret( 'myplugin/api-key', 'value' );

		$this->assertWPError( $other, 'A secret the plugin did not allow should still be refused.' );
		$this->assertSame( WP_SECRETS_ERROR_NOT_ALLOWED, $other->get_error_code(), 'The refusal should use the not allowed error code.' );

		$network = wp_set_network_secret( 'myplugin/editor-key', 'value' );

		$this->assertWPError( $network, 'Allowing a site-scope secret should not allow the network-scope secret of the same name.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertWPError( wp_set_secret( 'myplugin/editor-key', 'value' ), 'Another user with the same role should still be refused.' );
	}

	/**
	 * Ensure that a plugin can stop a user who can manage secrets from changing one of them.
	 *
	 * @covers ::_wp_secrets_check_manage_capability
	 * @covers ::map_meta_cap
	 */
	public function test_a_plugin_can_refuse_one_secret_to_a_user_who_can_manage_secrets(): void {
		$this->set_current_user_to_secrets_manager();

		add_filter(
			'map_meta_cap',
			static function ( $caps, $cap, $user_id, $args ) {
				if ( 'manage_secret' === $cap && 'myplugin/locked' === $args[0] ) {
					return array( 'do_not_allow' );
				}

				return $caps;
			},
			10,
			4
		);

		$result = wp_set_secret( 'myplugin/locked', 'value' );

		$this->assertWPError( $result, 'The secret the plugin locked should be refused.' );
		$this->assertSame( WP_SECRETS_ERROR_NOT_ALLOWED, $result->get_error_code(), 'The refusal should use the not allowed error code.' );
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'value' ), 'A secret the plugin did not lock should still be stored.' );
	}

	/**
	 * Stores 'myplugin/api-key' in both scopes with a current and a previous value,
	 * and an option to import, as a user who can manage secrets.
	 *
	 * Leaves that user as the current user.
	 */
	private function seed_secret(): void {
		$this->set_current_user_to_secrets_manager();

		update_option( 'myplugin_legacy_api_key', 'legacy-value' );

		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'first-value' ), 'Seeding the site-scope secret should succeed.' );
		$this->assertTrue( wp_set_secret( 'myplugin/api-key', 'second-value' ), 'Rotating the site-scope secret should succeed.' );
		$this->assertTrue( wp_set_network_secret( 'myplugin/api-key', 'first-value' ), 'Seeding the network-scope secret should succeed.' );
		$this->assertTrue( wp_set_network_secret( 'myplugin/api-key', 'second-value' ), 'Rotating the network-scope secret should succeed.' );
	}
}
