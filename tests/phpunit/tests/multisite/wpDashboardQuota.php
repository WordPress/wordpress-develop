<?php

/**
 * Tests specific to the upload quota display functions in multisite,
 * `wp_dashboard_quota()` and `display_space_usage()`.
 *
 * These tests filter `get_space_allowed` and `pre_get_space_used`
 * as those functions are tested elsewhere.
 *
 * @group ms-required
 * @group multisite
 */
class Tests_Multisite_WpDashboardQuota extends WP_UnitTestCase {

	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/dashboard.php';
		require_once ABSPATH . 'wp-admin/includes/ms.php';
	}

	public function set_up() {
		parent::set_up();

		update_site_option( 'upload_space_check_disabled', false );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Sets the upload quota and the space used, both in megabytes.
	 *
	 * @param int|float $allowed Upload quota.
	 * @param int|float $used    Space used.
	 */
	private function set_quota( $allowed, $used ) {
		add_filter(
			'get_space_allowed',
			static function () use ( $allowed ) {
				return $allowed;
			}
		);
		add_filter(
			'pre_get_space_used',
			static function () use ( $used ) {
				return $used;
			}
		);
	}

	/**
	 * `wp_dashboard_quota()` must not throw a `DivisionByZeroError`
	 * for a quota that is zero or negative.
	 *
	 * @ticket 65439
	 *
	 * @dataProvider data_unusable_quotas
	 *
	 * @param int|float $allowed Upload quota.
	 * @param int|float $used    Space used.
	 */
	public function test_wp_dashboard_quota_does_not_throw_for_unusable_quota( $allowed, $used ) {
		$this->set_quota( $allowed, $used );

		ob_start();
		wp_dashboard_quota();
		ob_end_clean();

		$this->expectNotToPerformAssertions();
	}

	/**
	 * `display_space_usage()` must not throw a `DivisionByZeroError`
	 * for a quota that is zero or negative.
	 *
	 * @ticket 65439
	 *
	 * @dataProvider data_unusable_quotas
	 *
	 * @param int|float $allowed Upload quota.
	 * @param int|float $used    Space used.
	 */
	public function test_display_space_usage_does_not_throw_for_unusable_quota( $allowed, $used ) {
		$this->set_quota( $allowed, $used );

		ob_start();
		display_space_usage();
		ob_end_clean();

		$this->expectNotToPerformAssertions();
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_unusable_quotas() {
		return array(
			'zero quota, nothing used'   => array( 0, 0 ),
			'zero quota, space used'     => array( 0, 5 ),
			'negative quota'             => array( -1, 0 ),
			'negative quota, space used' => array( -1, 5 ),
		);
	}

	/**
	 * `wp_dashboard_quota()` must still render the widget for a normal quota.
	 *
	 * @ticket 65439
	 */
	public function test_wp_dashboard_quota_renders_output_for_normal_quota() {
		$this->set_quota( 100, 5 );

		ob_start();
		wp_dashboard_quota();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Storage Space', $output );
	}

	/**
	 * `display_space_usage()` must still render the usage for a normal quota.
	 *
	 * @ticket 65439
	 */
	public function test_display_space_usage_renders_output_for_normal_quota() {
		$this->set_quota( 100, 5 );

		ob_start();
		display_space_usage();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Used:', $output );
	}
}
