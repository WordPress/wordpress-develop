<?php

/**
 * @group phpunit
 * @group ms-required
 */
class Tests_Includes_TearDownAfterClass extends WP_UnitTestCase {

	/**
	 * The super admins before the tests ran.
	 *
	 * @var string[]
	 */
	private static $site_admins;

	public static function wpSetUpBeforeClass() {
		self::$site_admins = get_site_option( 'site_admins' );
	}

	/**
	 * Leaves the rolled back value in the cache, as the REST comments tests do.
	 */
	public function test_site_admins_changed_in_a_test() {
		update_site_option( 'site_admins', array( 'superadmin' ) );

		$this->assertSame( array( 'superadmin' ), get_site_option( 'site_admins' ) );
	}

	public static function tear_down_after_class() {
		parent::tear_down_after_class();

		self::assertSame(
			self::$site_admins,
			get_site_option( 'site_admins' ),
			'The class cleanup should keep the super admins the rollback restored.'
		);
	}
}
