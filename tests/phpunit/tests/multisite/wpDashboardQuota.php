<?php

/**
 * Tests specific to `wp_dashboard_quota()` in multisite.
 *
 * These tests filter `get_space_allowed` and `pre_get_space_used`
 * as those functions are tested elsewhere.
 *
 * @ticket 65439
 * @group ms-required
 * @group multisite
 */

class Tests_Multisite_WpDashboardQuota extends WP_UnitTestCase {

	public static function set_up_before_class() {
		parent::set_up_before_class();

		if ( ! function_exists( 'wp_dashboard_quota' ) ) {
			require_once ABSPATH . 'wp-admin/includes/dashboard.php';
		}
	}

	public function set_up() {
		parent::set_up();
		update_site_option( 'upload_space_check_disabled', false );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * wp_dashboard_quota() must not throw a DivisionByZeroError when
	 * quota is 0 and used space is 0.
	 *
	 * @ticket 65439
	 */
	public function test_quota_zero_used_zero_does_not_throw() {
		add_filter( 'get_space_allowed', '__return_zero' );
		add_filter( 'pre_get_space_used', '__return_zero' );

		$threw = false;
		try {
			ob_start();
			wp_dashboard_quota();
			ob_end_clean();
		} catch ( \DivisionByZeroError $e ) {
			ob_end_clean();
			$threw = true;
		}

		remove_filter( 'get_space_allowed', '__return_zero' );
		remove_filter( 'pre_get_space_used', '__return_zero' );

		$this->assertFalse( $threw, 'wp_dashboard_quota() must not throw DivisionByZeroError when quota is 0.' );
	}

	/**
	 * wp_dashboard_quota() must not throw when quota is 0 and space is used.
	 *
	 * @ticket 65439
	 */
	public function test_quota_zero_used_nonzero_does_not_throw() {
		add_filter( 'get_space_allowed', '__return_zero' );
		add_filter( 'pre_get_space_used', array( $this, '_filter_space_5' ) );

		$threw = false;
		try {
			ob_start();
			wp_dashboard_quota();
			ob_end_clean();
		} catch ( \DivisionByZeroError $e ) {
			ob_end_clean();
			$threw = true;
		}

		remove_filter( 'get_space_allowed', '__return_zero' );
		remove_filter( 'pre_get_space_used', array( $this, '_filter_space_5' ) );

		$this->assertFalse( $threw, 'wp_dashboard_quota() must not throw DivisionByZeroError when quota is 0 and space is used.' );
	}

	/**
	 * wp_dashboard_quota() must not throw when quota is negative.
	 *
	 * @ticket 65439
	 */
	public function test_quota_negative_does_not_throw() {
		add_filter( 'get_space_allowed', array( $this, '_filter_space_negative' ) );
		add_filter( 'pre_get_space_used', '__return_zero' );

		$threw = false;
		try {
			ob_start();
			wp_dashboard_quota();
			ob_end_clean();
		} catch ( \DivisionByZeroError $e ) {
			ob_end_clean();
			$threw = true;
		}

		remove_filter( 'get_space_allowed', array( $this, '_filter_space_negative' ) );
		remove_filter( 'pre_get_space_used', '__return_zero' );

		$this->assertFalse( $threw, 'wp_dashboard_quota() must not throw when quota is negative.' );
	}

	/**
	 * wp_dashboard_quota() must render output normally for a non-zero quota.
	 *
	 * @ticket 65439
	 */
	public function test_quota_nonzero_renders_output() {
		add_filter( 'get_space_allowed', array( $this, '_filter_space_100' ) );
		add_filter( 'pre_get_space_used', array( $this, '_filter_space_5' ) );

		ob_start();
		wp_dashboard_quota();
		$output = ob_get_clean();

		remove_filter( 'get_space_allowed', array( $this, '_filter_space_100' ) );
		remove_filter( 'pre_get_space_used', array( $this, '_filter_space_5' ) );

		$this->assertStringContainsString( 'Storage Space', $output, 'wp_dashboard_quota() must render the Storage Space widget for a normal quota.' );
	}

	/**
	 * wp_dashboard_quota() must produce no output when quota is 0
	 * (early return, nothing to display).
	 *
	 * @ticket 65439
	 */
	public function test_quota_zero_produces_no_output() {
		add_filter( 'get_space_allowed', '__return_zero' );
		add_filter( 'pre_get_space_used', '__return_zero' );

		ob_start();
		wp_dashboard_quota();
		$output = ob_get_clean();

		remove_filter( 'get_space_allowed', '__return_zero' );
		remove_filter( 'pre_get_space_used', '__return_zero' );

		$this->assertSame( '', $output, 'wp_dashboard_quota() must produce no output when quota is 0.' );
	}

	public function _filter_space_5() {
		return 5;
	}

	public function _filter_space_100() {
		return 100;
	}

	public function _filter_space_negative() {
		return -1;
	}

	/**
	 * display_space_usage() must not throw a DivisionByZeroError when quota is 0.
	 *
	 * @ticket 65439
	 */
	public function test_display_space_usage_quota_zero_does_not_throw() {
		add_filter( 'get_space_allowed', '__return_zero' );
		add_filter( 'pre_get_space_used', '__return_zero' );

		$threw = false;
		try {
			ob_start();
			display_space_usage();
			ob_end_clean();
		} catch ( \DivisionByZeroError $e ) {
			ob_end_clean();
			$threw = true;
		}

		remove_filter( 'get_space_allowed', '__return_zero' );
		remove_filter( 'pre_get_space_used', '__return_zero' );

		$this->assertFalse( $threw, 'display_space_usage() must not throw DivisionByZeroError when quota is 0.' );
	}

	/**
	 * display_space_usage() must render output normally for a non-zero quota.
	 *
	 * @ticket 65439
	 */
	public function test_display_space_usage_quota_nonzero_renders_output() {
		add_filter( 'get_space_allowed', array( $this, '_filter_space_100' ) );
		add_filter( 'pre_get_space_used', array( $this, '_filter_space_5' ) );

		ob_start();
		display_space_usage();
		$output = ob_get_clean();

		remove_filter( 'get_space_allowed', array( $this, '_filter_space_100' ) );
		remove_filter( 'pre_get_space_used', array( $this, '_filter_space_5' ) );

		$this->assertStringContainsString( 'Used:', $output, 'display_space_usage() must render usage output for a normal quota.' );
	}
}
