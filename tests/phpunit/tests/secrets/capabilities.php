<?php
/**
 * Tests for the Secrets API's two capabilities: manage_secrets, granted to
 * administrators by populate_roles() and upgrade_720(), and manage_network_secrets,
 * held only by super admins.
 *
 * @ticket 66187
 *
 * @group secrets
 * @group capabilities
 */
class Tests_Secrets_Capabilities extends WP_UnitTestCase {

	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/schema.php';
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	}

	public function tear_down() {
		$administrator = get_role( 'administrator' );

		if ( $administrator ) {
			$administrator->add_cap( WP_SECRETS_CAP_MANAGE );
		}

		parent::tear_down();
	}

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
	 * Ensure that the administrator role has manage_secrets on a fresh install.
	 *
	 * The test suite's install runs populate_roles(), so no setup is needed to observe
	 * the state a new site starts in.
	 *
	 * @covers ::populate_roles_720
	 */
	public function test_a_fresh_install_grants_manage_secrets_to_administrator(): void {
		$this->assertTrue( $this->role( 'administrator' )->has_cap( WP_SECRETS_CAP_MANAGE ), 'The administrator role should have manage_secrets on a fresh install.' );
	}

	/**
	 * Ensure that no default role other than administrator is granted manage_secrets.
	 *
	 * @covers ::populate_roles_720
	 *
	 * @dataProvider data_no_other_role_is_granted_manage_secrets
	 *
	 * @param string $role A default role other than administrator.
	 */
	public function test_no_other_role_is_granted_manage_secrets( $role ): void {
		$this->assertFalse( $this->role( $role )->has_cap( WP_SECRETS_CAP_MANAGE ), 'Role should not be granted capability' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function data_no_other_role_is_granted_manage_secrets() {
		return array(
			'editor'      => array( 'editor' ),
			'author'      => array( 'author' ),
			'contributor' => array( 'contributor' ),
			'subscriber'  => array( 'subscriber' ),
		);
	}

	/**
	 * Ensure that populate_roles_720() grants manage_secrets to the administrator role.
	 *
	 * @covers ::populate_roles_720
	 */
	public function test_populate_roles_720_grants_manage_secrets_to_administrator(): void {
		$this->role( 'administrator' )->remove_cap( WP_SECRETS_CAP_MANAGE );

		populate_roles_720();

		$this->assertTrue( $this->role( 'administrator' )->has_cap( WP_SECRETS_CAP_MANAGE ), 'populate_roles_720() should grant manage_secrets to the administrator role.' );
	}

	/**
	 * Ensure that upgrade_720() grants manage_secrets only to sites below database version 64337.
	 *
	 * Simulates upgrades from several database versions by overriding $wp_current_db_version.
	 * Sites already at or past the gate must not have the capability re-added, so an
	 * administrator's deliberate removal of it survives later upgrades.
	 *
	 * @covers ::upgrade_720
	 *
	 * @dataProvider data_upgrade_720_grants_manage_secrets_below_its_db_version
	 *
	 * @param int  $db_version     The database version being upgraded from.
	 * @param bool $expect_granted Whether the upgrade should grant the capability.
	 */
	public function test_upgrade_720_grants_manage_secrets_below_its_db_version( $db_version, $expect_granted ): void {
		global $wp_current_db_version;

		$saved                 = $wp_current_db_version;
		$wp_current_db_version = $db_version;

		$this->role( 'administrator' )->remove_cap( WP_SECRETS_CAP_MANAGE );

		upgrade_720();

		$wp_current_db_version = $saved;

		$this->assertSame( $expect_granted, $this->role( 'administrator' )->has_cap( WP_SECRETS_CAP_MANAGE ), 'The administrator role should have manage_secrets only when upgrading from below 64337.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: int, 1: bool}>
	 */
	public static function data_upgrade_720_grants_manage_secrets_below_its_db_version() {
		return array(
			'upgrading from 7.0'              => array( 61644, true ),
			'upgrading from just below 7.2'   => array( 64336, true ),
			'already at the 7.2 db version'   => array( 64337, false ),
			'already past the 7.2 db version' => array( 64400, false ),
		);
	}

	/**
	 * Ensure that a user with the administrator role can manage secrets.
	 *
	 * @covers ::populate_roles_720
	 */
	public function test_administrator_user_has_manage_secrets(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->assertTrue( user_can( $user_id, WP_SECRETS_CAP_MANAGE ), 'An administrator user should have manage_secrets.' );
	}

	/**
	 * Ensure that a user with the subscriber role cannot manage secrets.
	 *
	 * @covers ::populate_roles_720
	 */
	public function test_subscriber_does_not_have_manage_secrets(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$this->assertFalse( user_can( $user_id, WP_SECRETS_CAP_MANAGE ), 'A subscriber user should not have manage_secrets.' );
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
