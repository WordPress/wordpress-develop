<?php
/**
 * Tests for Distributed Editing reason-code helpers.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 */

require_once ABSPATH . WPINC . '/de-rtc.php';

class Tests_DE_RTC_Reason_Codes extends WP_UnitTestCase {

	/**
	 * @covers ::wp_de_rtc_get_reason_codes
	 */
	public function test_reason_codes_include_initial_canonical_list() {
		$codes = wp_de_rtc_get_reason_codes();

		$this->assertSame(
				array(
					'de_rtc_missing_sync_meta',
					'de_rtc_sync_meta_restored_from_revision',
					'de_rtc_sync_meta_repaired_from_body',
					'de_rtc_sync_meta_empty_automerge_import',
					'de_rtc_sync_meta_unrecoverable',
					'de_rtc_external_content_mismatch',
				'de_rtc_base_version_stale',
				'stale_base_version_rejected',
				'de_rtc_live_session_newer_than_restored_meta',
				'de_rtc_rebase_failed',
				'de_rtc_sync_meta_tampered',
				'de_rtc_unfiltered_html_would_change_content',
				'de_rtc_review_approval_requires_unfiltered_html',
				'de_rtc_feature_disabled',
				'de_rtc_malformed_sync_payload',
				'de_rtc_unknown_sync_meta_format',
				'de_rtc_presence_storage_unavailable',
				'de_rtc_review_item_storage_unavailable',
				'de_rtc_review_item_limit_exceeded',
				'de_rtc_review_item_payload_too_large',
				'de_rtc_review_item_not_found',
				'de_rtc_storage_failure',
			),
			array_keys( $codes )
		);
	}

	/**
	 * @dataProvider data_reason_code_statuses
	 *
	 * @covers ::wp_de_rtc_get_reason_codes
	 *
	 * @param string $reason_code Reason code.
	 * @param int    $status      Expected HTTP status.
	 */
	public function test_reason_codes_have_expected_status_mapping( $reason_code, $status ) {
		$codes = wp_de_rtc_get_reason_codes();

		$this->assertArrayHasKey( $reason_code, $codes );
		$this->assertSame( $status, $codes[ $reason_code ] );
	}

	/**
	 * Data provider for canonical DE-RTC reason-code statuses.
	 *
	 * @return array[]
	 */
	public function data_reason_code_statuses() {
		return array(
				'missing sync meta'                     => array( 'de_rtc_missing_sync_meta', 409 ),
				'sync meta restored from revision'      => array( 'de_rtc_sync_meta_restored_from_revision', 409 ),
				'sync meta repaired from body'          => array( 'de_rtc_sync_meta_repaired_from_body', 409 ),
				'empty Automerge import'                      => array( 'de_rtc_sync_meta_empty_automerge_import', 409 ),
				'sync meta unrecoverable'               => array( 'de_rtc_sync_meta_unrecoverable', 409 ),
			'external content mismatch'             => array( 'de_rtc_external_content_mismatch', 409 ),
			'base version stale'                    => array( 'de_rtc_base_version_stale', 409 ),
			'stale base version rejected'           => array( 'stale_base_version_rejected', 409 ),
			'live session newer than restored meta' => array( 'de_rtc_live_session_newer_than_restored_meta', 409 ),
			'rebase failed'                         => array( 'de_rtc_rebase_failed', 409 ),
			'sync meta tampered'                    => array( 'de_rtc_sync_meta_tampered', 403 ),
			'unfiltered html would change content'  => array( 'de_rtc_unfiltered_html_would_change_content', 403 ),
			'review approval requires unfiltered html' => array( 'de_rtc_review_approval_requires_unfiltered_html', 403 ),
			'feature disabled'                      => array( 'de_rtc_feature_disabled', 403 ),
			'malformed sync payload'                => array( 'de_rtc_malformed_sync_payload', 400 ),
			'unknown sync meta format'              => array( 'de_rtc_unknown_sync_meta_format', 400 ),
			'presence storage unavailable'          => array( 'de_rtc_presence_storage_unavailable', 503 ),
			'review item storage unavailable'       => array( 'de_rtc_review_item_storage_unavailable', 503 ),
			'review item limit exceeded'            => array( 'de_rtc_review_item_limit_exceeded', 429 ),
			'review item payload too large'         => array( 'de_rtc_review_item_payload_too_large', 413 ),
			'review item not found'                 => array( 'de_rtc_review_item_not_found', 404 ),
			'storage failure'                       => array( 'de_rtc_storage_failure', 500 ),
		);
	}
}
