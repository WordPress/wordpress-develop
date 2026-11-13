<?php
/**
 * Tests for {@see upgrade_network()}.
 *
 * @group ms-required
 * @group multisite
 * @group upgrade
 *
 * @covers ::upgrade_network
 */
class Tests_Multisite_UpgradeNetwork extends WP_UnitTestCase {

	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	}

	/**
	 * Tests that the network option set by the removed compression test is deleted below its database version.
	 *
	 * @ticket 65980
	 *
	 * @dataProvider data_can_compress_scripts_deletion
	 *
	 * @param int  $db_version     Database version being upgraded from.
	 * @param bool $expect_deleted Whether the option should be deleted.
	 */
	public function test_can_compress_scripts_is_deleted_below_its_db_version( int $db_version, bool $expect_deleted ): void {
		global $wp_current_db_version;

		update_site_option( 'can_compress_scripts', 1 );

		$saved                 = $wp_current_db_version;
		$wp_current_db_version = $db_version;

		upgrade_network();

		$wp_current_db_version = $saved;

		$this->assertSame( $expect_deleted ? false : 1, get_site_option( 'can_compress_scripts' ) );
	}

	/**
	 * Data provider for test_can_compress_scripts_is_deleted_below_its_db_version().
	 *
	 * @return array<non-falsy-string, array{ db_version: int, expect_deleted: bool }>
	 */
	public static function data_can_compress_scripts_deletion(): array {
		return array(
			'upgrading from just below the 7.2 db version' => array(
				'db_version'     => 64485,
				'expect_deleted' => true,
			),
			'already at the 7.2 db version'                => array(
				'db_version'     => 64486,
				'expect_deleted' => false,
			),
		);
	}
}
