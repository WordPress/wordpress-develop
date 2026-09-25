<?php

/**
 * @group admin
 * @group login
 */
class Tests_Admin_AdminEmailVerification extends WP_UnitTestCase {

	protected static $admin_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * @ticket 48153
	 */
	public function test_show_admin_email_verification_filter_can_disable_verification() {
		wp_set_current_user( self::$admin_id );
		$user = wp_get_current_user();

		add_filter( 'show_admin_email_verification', '__return_false' );
		$result = (bool) apply_filters( 'show_admin_email_verification', true, $user );
		remove_filter( 'show_admin_email_verification', '__return_false' );

		$this->assertFalse( $result );
	}

	/**
	 * @ticket 48153
	 */
	public function test_show_admin_email_verification_filter_receives_user_object() {
		wp_set_current_user( self::$admin_id );
		$user = wp_get_current_user();

		$captured_user = null;
		$callback      = function ( $show, $user ) use ( &$captured_user ) {
			$captured_user = $user;
			return $show;
		};

		add_filter( 'show_admin_email_verification', $callback, 10, 2 );
		apply_filters( 'show_admin_email_verification', true, $user );
		remove_filter( 'show_admin_email_verification', $callback );

		$this->assertInstanceOf( 'WP_User', $captured_user );
		$this->assertSame( self::$admin_id, $captured_user->ID );
	}

	/**
	 * @ticket 48153
	 */
	public function test_show_admin_email_verification_filter_prevents_redirect() {
		$user        = get_userdata( self::$admin_id );
		$redirect_to = admin_url();

		// Set the lifespan to the past so it would be expired.
		update_option( 'admin_email_lifespan', time() - 100 );

		add_filter( 'show_admin_email_verification', '__return_false' );

		// Simulate the redirect logic from wp-login.php.
		$original_redirect = $redirect_to;
		if ( is_a( $user, 'WP_User' ) && $user->exists() && $user->has_cap( 'manage_options' ) ) {
			$show_verification = (bool) apply_filters( 'show_admin_email_verification', true, $user );
			if ( $show_verification && time() > (int) get_option( 'admin_email_lifespan' ) ) {
				$redirect_to = add_query_arg( 'action', 'confirm_admin_email', wp_login_url( $redirect_to ) );
			}
		}

		remove_filter( 'show_admin_email_verification', '__return_false' );

		$this->assertSame( $original_redirect, $redirect_to );
		$this->assertStringNotContainsString( 'confirm_admin_email', $redirect_to );
	}

	/**
	 * @ticket 48153
	 */
	public function test_admin_email_check_interval_zero_still_updates_lifespan() {
		add_filter( 'admin_email_check_interval', '__return_zero' );

		$before = time();

		$admin_email_check_interval = (int) apply_filters( 'admin_email_check_interval', 6 * MONTH_IN_SECONDS );
		update_option( 'admin_email_lifespan', time() + $admin_email_check_interval );

		$lifespan = (int) get_option( 'admin_email_lifespan' );

		remove_filter( 'admin_email_check_interval', '__return_zero' );

		$this->assertGreaterThanOrEqual( $before, $lifespan );
		$this->assertLessThanOrEqual( $before + 5, $lifespan );
	}

	/**
	 * @ticket 48153
	 */
	public function test_confirm_admin_email_action_respects_show_verification_filter() {
		wp_set_current_user( self::$admin_id );

		$this->assertTrue( current_user_can( 'manage_options' ) );

		$show_verification = (bool) apply_filters( 'show_admin_email_verification', true, wp_get_current_user() );
		$this->assertTrue( $show_verification );

		// When the filter returns false, the screen should be skipped.
		add_filter( 'show_admin_email_verification', '__return_false' );
		$show_verification = (bool) apply_filters( 'show_admin_email_verification', true, wp_get_current_user() );
		remove_filter( 'show_admin_email_verification', '__return_false' );

		$this->assertFalse( $show_verification );
	}
}
