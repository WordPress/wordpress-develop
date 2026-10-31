<?php

/**
 * @group phpunit
 * @group ms-required
 */
class Tests_Includes_TearDownAfterClass extends WP_UnitTestCase {

	/**
	 * The super admins before the class fixtures ran.
	 *
	 * @var string[]
	 */
	private static $site_admins;

	/**
	 * Replaces the super admins and commits them, as the REST posts tests do.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$site_admins = get_site_option( 'site_admins' );

		$factory->user->create( array( 'user_login' => 'superadmin' ) );
		update_site_option( 'site_admins', array( 'superadmin' ) );
	}

	/**
	 * Leaves the original super admins in the cache after the rollback, while the
	 * database still has the class fixture ones.
	 */
	public function test_site_admins_changed_in_a_test() {
		update_site_option( 'site_admins', self::$site_admins );

		$this->assertSame( self::$site_admins, get_site_option( 'site_admins' ) );
	}

	public static function tear_down_after_class() {
		parent::tear_down_after_class();

		self::assertSame(
			self::$site_admins,
			get_site_option( 'site_admins' ),
			'The class cleanup should restore the super admins from before the class fixtures.'
		);
	}
}

/**
 * Runs after the class above, and so after its cleanup.
 *
 * @group phpunit
 * @group ms-required
 */
class Tests_Includes_TearDownAfterClass_Isolated extends WP_UnitTestCase {

	/**
	 * An isolated test no longer reinstalls WordPress, so it sees what the class cleanup left.
	 *
	 * @depends Tests_Includes_TearDownAfterClass::test_site_admins_changed_in_a_test
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_default_administrator_is_super_admin() {
		$this->assertTrue( is_super_admin( 1 ) );
	}
}
