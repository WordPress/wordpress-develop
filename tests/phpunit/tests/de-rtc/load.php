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
		$this->assertTrue( function_exists( 'wp_de_rtc_dry_run_sync_meta_recovery_update' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_apply_sync_meta_recovery_update' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_is_enabled' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_rest_presence_endpoint' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_get_post_presence_read_snapshot' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_get_presence_storage_schema' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_install_presence_table' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_get_presence_storage_readiness' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_get_presence_storage_setup_admin_action_state' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_run_presence_storage_setup_admin_action' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_cleanup_presence_records' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_get_post_presence_storage_entries' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_get_presence_actor_hash' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_rest_presence_heartbeat_endpoint' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_record_presence_heartbeat' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_rest_pre_insert_stale_base_probe' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_rest_retry_submit_endpoint' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_get_retry_submit_acceptance_result' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_rest_retry_save_endpoint' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_save_retry_submitted_post' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_get_block_identity_contract_required_fields' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_validate_block_identity_sync_meta_contract' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_validate_block_identity_request_proof' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_rest_review_approval_endpoint' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_get_unfiltered_html_review_approval_result' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_rest_fresh_review_request_endpoint' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_get_fresh_review_request_result' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_rest_fresh_review_decision_endpoint' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_get_fresh_review_decision_result' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_create_opaque_fresh_review_request_record' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_classify_kses_risky_block_review_items' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_cleanup_opaque_review_approval_proof_token_audit_records' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_schedule_opaque_review_approval_proof_token_audit_cleanup' ) );
		$this->assertTrue( function_exists( 'wp_de_rtc_run_opaque_review_approval_proof_token_audit_cleanup' ) );
	}
}
