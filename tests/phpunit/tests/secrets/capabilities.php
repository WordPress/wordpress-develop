<?php
/**
 * Tests for the Secrets API's two capabilities: manage_secrets, granted to
 * administrators by populate_roles() and upgrade_720(), and manage_network_secrets,
 * held only by super admins.
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

	public function test_capability_constant_values(): void {
		$this->assertSame( 'manage_secrets', WP_SECRETS_CAP_MANAGE ); // @phpstan-ignore method.alreadyNarrowedType (Intentionally pinning the value of a public constant.)
		$this->assertSame( 'manage_network_secrets', WP_SECRETS_CAP_MANAGE_NETWORK ); // @phpstan-ignore method.alreadyNarrowedType (Intentionally pinning the value of a public constant.)
	}

	public function test_a_fresh_install_grants_manage_secrets_to_administrator(): void {
		$this->assertTrue( $this->role( 'administrator' )->has_cap( WP_SECRETS_CAP_MANAGE ) );
	}

	public function test_no_other_role_is_granted_manage_secrets(): void {
		foreach ( array( 'editor', 'author', 'contributor', 'subscriber' ) as $role ) {
			$this->assertFalse( $this->role( $role )->has_cap( WP_SECRETS_CAP_MANAGE ), $role );
		}
	}

	public function test_populate_roles_720_grants_manage_secrets_to_administrator(): void {
		$this->role( 'administrator' )->remove_cap( WP_SECRETS_CAP_MANAGE );

		populate_roles_720();

		$this->assertTrue( $this->role( 'administrator' )->has_cap( WP_SECRETS_CAP_MANAGE ) );
	}

	/**
	 * @dataProvider data_upgrade_720_gate
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

		$this->assertSame( $expect_granted, $this->role( 'administrator' )->has_cap( WP_SECRETS_CAP_MANAGE ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: int, 1: bool}>
	 */
	public function data_upgrade_720_gate() {
		return array(
			'upgrading from 7.0'              => array( 61644, true ),
			'upgrading from just below 7.2'   => array( 61899, true ),
			'already at the 7.2 db version'   => array( 61900, false ),
			'already past the 7.2 db version' => array( 62000, false ),
		);
	}

	public function test_administrator_user_has_manage_secrets(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->assertTrue( user_can( $user_id, WP_SECRETS_CAP_MANAGE ) );
	}

	public function test_subscriber_does_not_have_manage_secrets(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$this->assertFalse( user_can( $user_id, WP_SECRETS_CAP_MANAGE ) );
	}

	/**
	 * @group ms-excluded
	 */
	public function test_single_site_administrator_does_not_have_manage_network_secrets(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->assertFalse( user_can( $user_id, WP_SECRETS_CAP_MANAGE_NETWORK ) );
	}

	/**
	 * @group ms-required
	 */
	public function test_only_super_admins_have_manage_network_secrets(): void {
		$administrator = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$super_admin   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $super_admin );

		$this->assertFalse( user_can( $administrator, WP_SECRETS_CAP_MANAGE_NETWORK ) );
		$this->assertTrue( user_can( $super_admin, WP_SECRETS_CAP_MANAGE_NETWORK ) );
	}
}
