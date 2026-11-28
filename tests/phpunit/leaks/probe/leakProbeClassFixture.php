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

	public static function tear_down_after_class() {
		parent::tear_down_after_class();

		// After the framework's flush, as a class's own tear_down_after_class() can, so a persistent cache keeps it.
		wp_cache_set( 'after_class', 'leaked', 'leak_probe' );
		wp_cache_set( 'leak_probe_class_cached_only', 'leaked', 'options' );

		// PHPUnit restores both after each test, not after class hooks.
		chdir( sys_get_temp_dir() );
		ob_start();
	}

	public function test_fixture_user_exists() {
		$this->assertInstanceOf( WP_User::class, get_user_by( 'login', self::ADMIN ) );
	}
}
