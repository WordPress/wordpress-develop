<?php
/**
 * A class fixture commits network state the class cleanup must undo, the way
 * WP_Test_REST_Posts_Controller replaces the super admins with its fixture user.
 * With WP_TESTS_LEAKS_SCOPE=class, a broken cleanup shows as this class leaving `site_admins`.
 */
class Tests_Leak_Probe_Class_Fixture extends WP_UnitTestCase {

	const ADMIN = 'leakprobeadmin';

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$factory->user->create( array( 'user_login' => self::ADMIN ) );

		if ( is_multisite() ) {
			update_site_option( 'site_admins', array( self::ADMIN ) );
		}
	}

	public function test_fixture_user_exists() {
		$this->assertInstanceOf( WP_User::class, get_user_by( 'login', self::ADMIN ) );
	}
}
