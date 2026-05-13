<?php
/**
 * Tests for Distributed Editing core loading.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 */

class Tests_DE_RTC_Load extends WP_UnitTestCase {

	/**
	 * @coversNothing
	 */
	public function test_de_rtc_helpers_are_loaded_by_core() {
		$this->assertTrue( function_exists( 'wp_de_rtc_get_reason_codes' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_find_latest_revision_with_sync_meta' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_get_post_sync_meta_recovery_decision' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_plan_sync_meta_recovery_update' ) );
	}
}
