<?php
/**
 * Tests for the Secrets API's two capabilities: manage_secrets, a meta capability
 * that maps to manage_options, and manage_network_secrets, held only by super admins.
 *
 * @ticket 66187
 * @ticket 66696
 *
 * @group secrets
 * @group capabilities
 */
class Tests_Secrets_Capabilities extends WP_UnitTestCase {

	/**
	 * Returns a role that must exist, failing the test if it does not.
	 *
	 * @param string $role Role name.
	 * @return WP_Role
	 */
	private function role( $role ) {
		$role_object = get_role( $role );

		$this->assertInstanceOf( WP_Role::class, $role_object, "The \"{$role}\" role does not exist." );

		return $role_object;
	}

	/**
	 * Ensure that the capability constants hold the manage_secrets and manage_network_secrets names.
	 *
	 * The capability names are a public contract: plugins and role editors check the
	 * strings directly, so the constants must not drift from them.
	 *
	 * @coversNothing
	 */
	public function test_capability_constant_values(): void {
		$this->assertSame( 'manage_secrets', WP_SECRETS_CAP_MANAGE, 'WP_SECRETS_CAP_MANAGE should be manage_secrets.' ); // @phpstan-ignore method.alreadyNarrowedType (Intentionally pinning the value of a public constant.)
		$this->assertSame( 'manage_network_secrets', WP_SECRETS_CAP_MANAGE_NETWORK, 'WP_SECRETS_CAP_MANAGE_NETWORK should be manage_network_secrets.' ); // @phpstan-ignore method.alreadyNarrowedType (Intentionally pinning the value of a public constant.)
	}

	/**
	 * Ensure that manage_secrets maps to manage_options.
	 *
	 * @ticket 66696
	 *
	 * @covers ::map_meta_cap
	 */
	public function test_manage_secrets_maps_to_manage_options(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$this->assertSame( array( 'manage_options' ), map_meta_cap( WP_SECRETS_CAP_MANAGE, $user_id ), 'manage_secrets should map to manage_options.' );
	}

	/**
	 * Ensure that manage_secrets is not stored on any default role.
	 *
	 * It is a meta capability, so no role should carry it in the database.
	 *
	 * @ticket 66696
	 *
	 * @covers ::populate_roles
	 *
	 * @dataProvider data_default_roles
	 *
	 * @param string $role A default role.
	 */
	public function test_manage_secrets_is_not_stored_on_a_role( $role ): void {
		$this->assertArrayNotHasKey( WP_SECRETS_CAP_MANAGE, $this->role( $role )->capabilities, 'manage_secrets should not be stored on a role.' );
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
	 * Ensure that only users of a role with manage_options can manage secrets.
	 *
	 * @ticket 66696
	 *
	 * @covers ::map_meta_cap
	 *
	 * @dataProvider data_roles_and_manage_secrets
	 *
	 * @param string $role     A default role.
	 * @param bool   $expected Whether a user with the role should have manage_secrets.
	 */
	public function test_only_roles_with_manage_options_have_manage_secrets( $role, $expected ): void {
		$user_id = self::factory()->user->create( array( 'role' => $role ) );

		$this->assertSame( $expected, user_can( $user_id, WP_SECRETS_CAP_MANAGE ), "Unexpected manage_secrets result for the \"{$role}\" role." );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public static function data_roles_and_manage_secrets() {
		return array(
			'administrator' => array( 'administrator', true ),
			'editor'        => array( 'editor', false ),
			'author'        => array( 'author', false ),
			'contributor'   => array( 'contributor', false ),
			'subscriber'    => array( 'subscriber', false ),
		);
	}

	/**
	 * Ensure that manage_secrets follows manage_options when that capability is granted to another role.
	 *
	 * @ticket 66696
	 *
	 * @covers ::map_meta_cap
	 */
	public function test_manage_secrets_follows_manage_options(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		$user    = get_userdata( $user_id );

		$this->assertInstanceOf( WP_User::class, $user );

		$user->add_cap( 'manage_options' );

		$this->assertTrue( user_can( $user_id, WP_SECRETS_CAP_MANAGE ), 'A user granted manage_options should have manage_secrets.' );
	}

	/**
	 * Ensure that an administrator on a single site install does not have manage_network_secrets.
	 *
	 * @group ms-excluded
	 *
	 * @covers ::map_meta_cap
	 */
	public function test_single_site_administrator_does_not_have_manage_network_secrets(): void {
		$this->skipWithMultisite();

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->assertFalse( user_can( $user_id, WP_SECRETS_CAP_MANAGE_NETWORK ), 'A single site administrator should not have manage_network_secrets.' );
	}

	/**
	 * Ensure that only super admins have the manage_network_secrets capability.
	 *
	 * @group ms-required
	 *
	 * @covers ::map_meta_cap
	 */
	public function test_only_super_admins_have_manage_network_secrets(): void {
		$this->skipWithoutMultisite();

		$administrator = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$super_admin   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $super_admin );

		$this->assertFalse( user_can( $administrator, WP_SECRETS_CAP_MANAGE_NETWORK ), 'An administrator who is not a super admin should not have manage_network_secrets.' );
		$this->assertTrue( user_can( $super_admin, WP_SECRETS_CAP_MANAGE_NETWORK ), 'A super admin should have manage_network_secrets.' );
	}
}
