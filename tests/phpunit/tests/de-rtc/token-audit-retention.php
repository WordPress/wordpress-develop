<?php
/**
 * Tests for Distributed Editing opaque token audit retention.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 */

class Tests_DE_RTC_Token_Audit_Retention extends WP_UnitTestCase {

	protected static $admin_user_id;

	private $audit_option_names = array();
	private $transient_keys     = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$admin_user_id );
	}

	public function tear_down() {
		foreach ( array_unique( $this->audit_option_names ) as $option_name ) {
			delete_option( $option_name );
		}

		foreach ( array_unique( $this->transient_keys ) as $transient_key ) {
			delete_transient( $transient_key );
		}

		parent::tear_down();
	}

	/**
	 * @covers ::wp_de_rtc_create_opaque_review_approval_proof_token_envelope
	 * @covers ::wp_de_rtc_get_opaque_review_approval_proof_token_audit_public_record_id
	 * @covers ::wp_de_rtc_get_opaque_review_approval_proof_token_audit_retention_seconds
	 * @covers ::wp_de_rtc_get_opaque_review_approval_proof_token_audit_cleanup_eligible_at
	 * @covers ::wp_de_rtc_get_opaque_review_approval_proof_token_audit_record
	 * @covers ::wp_de_rtc_record_opaque_review_approval_proof_token_audit_event
	 * @covers ::wp_de_rtc_get_opaque_review_approval_proof_token_audit_public_evidence
	 */
	public function test_token_audit_public_record_id_is_support_safe_and_retention_metadata_is_recorded() {
		$post_id    = $this->create_sync_meta_post( 'audit correlation current content', '7' );
		$proof_data = $this->create_opaque_review_approval_proof_token_envelope( $post_id, '7', 'correlation' );
		$record     = wp_de_rtc_get_opaque_review_approval_proof_token_audit_record( $proof_data['envelope'] );

		$this->assertIsArray( $record );
		$this->assertArrayHasKey( 'public_record_id', $record );
		$this->assertSame( 1, preg_match( '/^de-rtc-audit-[a-f0-9]{24}$/', $record['public_record_id'] ) );
		$this->assertSame(
			wp_de_rtc_get_opaque_review_approval_proof_token_audit_public_record_id( $record['token_hash'], $post_id, '7' ),
			$record['public_record_id']
		);
		$this->assertSame( $record['public_record_id'], $proof_data['envelope']['token_audit']['public_record_id'] );
		$this->assertSame( 'opaque_review_approval_token_audit_option_retention_v1', $record['audit_retention_policy'] );
		$this->assertSame( wp_de_rtc_get_opaque_review_approval_proof_token_audit_retention_seconds(), $record['audit_retention_seconds'] );
		$this->assertSame(
			wp_de_rtc_get_opaque_review_approval_proof_token_audit_cleanup_eligible_at( $record, $record['audit_retention_seconds'] ),
			$record['audit_cleanup_eligible_at']
		);
		$this->assertGreaterThan( time(), $record['audit_cleanup_eligible_at'] );
		$this->assertSame( 1, $record['reviewed_block_item_count'] );

		$this->assertArrayNotHasKey( 'token_hash', $proof_data['envelope']['token_audit'] );
		$this->assert_audit_payload_omits_private_fields(
			$proof_data['envelope']['token_audit'],
			array(
				$proof_data['envelope']['token'],
				$record['token_hash'],
				$proof_data['proposed_content'],
				$proof_data['proof_signature'],
			)
		);
		$this->assert_audit_payload_omits_private_fields(
			$record,
			array(
				$proof_data['envelope']['token'],
				$proof_data['proposed_content'],
				$proof_data['proof_signature'],
			)
		);
	}

	/**
	 * @covers ::wp_de_rtc_cleanup_opaque_review_approval_proof_token_audit_records
	 * @covers ::wp_de_rtc_get_opaque_review_approval_proof_token_audit_cleanup_eligible_at
	 * @covers ::wp_de_rtc_create_opaque_review_approval_proof_token_envelope
	 * @covers ::wp_de_rtc_get_opaque_review_approval_proof_token_audit_record
	 */
	public function test_cleanup_deletes_only_retention_expired_audit_options_without_touching_transients_or_posts() {
		$now              = time();
		$post_id          = $this->create_sync_meta_post( 'audit cleanup current content', '7' );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$old_proof        = $this->create_opaque_review_approval_proof_token_envelope( $post_id, '7', 'cleanup-old' );
		$fresh_proof      = $this->create_opaque_review_approval_proof_token_envelope( $post_id, '7', 'cleanup-fresh' );
		$old_record       = $this->make_audit_record_cleanup_eligible( $old_proof['envelope'], $now );
		$fresh_record     = $this->make_audit_record_with_stale_stored_cleanup_time( $fresh_proof['envelope'], $now );
		$old_transient    = wp_de_rtc_get_opaque_review_approval_proof_token_transient_key_from_envelope( $old_proof['envelope'] );
		$fresh_transient  = wp_de_rtc_get_opaque_review_approval_proof_token_transient_key_from_envelope( $fresh_proof['envelope'] );
		$invalid_option   = 'de_rtc_review_approval_token_audit_invalid_' . wp_generate_password( 8, false, false );

		$this->audit_option_names[] = $invalid_option;
		add_option(
			$invalid_option,
			array(
				'type'                      => 'not_a_de_rtc_audit_record',
				'audit_cleanup_eligible_at' => $now - MINUTE_IN_SECONDS,
			),
			'',
			'no'
		);

		$this->assertNotFalse( get_transient( $old_transient ) );
		$this->assertNotFalse( get_transient( $fresh_transient ) );

		$cleanup = wp_de_rtc_cleanup_opaque_review_approval_proof_token_audit_records(
			array(
				'now'   => $now,
				'limit' => 200,
			)
		);

		$this->assertSame( 200, $cleanup['limit'] );
		$this->assertLessThanOrEqual( 200, $cleanup['scanned'] );
		$this->assertSame( 1, $cleanup['deleted'] );
		$this->assertContains( $old_record['public_record_id'], $cleanup['deleted_public_record_ids'] );
		$this->assertNotContains( $fresh_record['public_record_id'], $cleanup['deleted_public_record_ids'] );
		$this->assertFalse( $cleanup['deletes_proof_transients'] );
		$this->assertFalse( $cleanup['resolves_proof'] );
		$this->assertFalse( $cleanup['saves_post'] );
		$this->assertFalse( $cleanup['mutates_post_content'] );
		$this->assertFalse( $cleanup['creates_revision'] );
		$this->assertFalse( $cleanup['applies_recovery'] );
		$this->assertFalse( $cleanup['changes_locks'] );
		$this->assertFalse( $cleanup['affects_normal_save_paths'] );

		$this->assertNull( wp_de_rtc_get_opaque_review_approval_proof_token_audit_record( $old_proof['envelope'] ) );
		$this->assertIsArray( wp_de_rtc_get_opaque_review_approval_proof_token_audit_record( $fresh_proof['envelope'] ) );
		$this->assertSame( 'not_a_de_rtc_audit_record', get_option( $invalid_option )['type'] );
		$this->assertNotFalse( get_transient( $old_transient ) );
		$this->assertNotFalse( get_transient( $fresh_transient ) );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_cleanup_opaque_review_approval_proof_token_audit_records
	 */
	public function test_cleanup_limit_zero_scans_and_deletes_nothing() {
		$now          = time();
		$post_id      = $this->create_sync_meta_post( 'audit zero-limit current content', '7' );
		$proof_data   = $this->create_opaque_review_approval_proof_token_envelope( $post_id, '7', 'zero-limit' );
		$old_record   = $this->make_audit_record_cleanup_eligible( $proof_data['envelope'], $now );
		$transient_key = wp_de_rtc_get_opaque_review_approval_proof_token_transient_key_from_envelope( $proof_data['envelope'] );

		$cleanup = wp_de_rtc_cleanup_opaque_review_approval_proof_token_audit_records(
			array(
				'now'   => $now,
				'limit' => 0,
			)
		);

		$this->assertSame( 0, $cleanup['limit'] );
		$this->assertSame( 0, $cleanup['scanned'] );
		$this->assertSame( 0, $cleanup['deleted'] );
		$this->assertSame( array(), $cleanup['deleted_public_record_ids'] );
		$this->assertSame( $old_record, wp_de_rtc_get_opaque_review_approval_proof_token_audit_record( $proof_data['envelope'] ) );
		$this->assertNotFalse( get_transient( $transient_key ) );
	}

	private function create_sync_meta_post( $label, $version ) {
		$content_with_sync_meta = wp_de_rtc_add_sync_meta_to_post_content(
			'<!-- wp:paragraph --><p>' . $label . '.</p><!-- /wp:paragraph -->',
			'diff-match-patch',
			array(
				'version' => $version,
			)
		);

		$this->assertIsString( $content_with_sync_meta );

		return self::factory()->post->create(
			array(
				'post_author'  => self::$admin_user_id,
				'post_status'  => 'draft',
				'post_content' => $content_with_sync_meta,
			)
		);
	}

	private function create_opaque_review_approval_proof_token_envelope( $post_id, $server_version, $label ) {
		$post             = get_post( $post_id );
		$issued_at        = time();
		$proposed_content = '<!-- wp:html --><iframe src="https://example.com/' . sanitize_key( $label ) . '"></iframe><!-- /wp:html -->';
		$candidate_hash   = hash( 'sha256', $proposed_content . '|candidate|' . $label );
		$proof_signature  = 'test-proof-signature-must-not-leak-' . sanitize_key( $label );
		$proof            = array(
			'type'                            => 'unfiltered_html_retry_save_review_approval',
			'status'                          => 'approved_by_unfiltered_html_reviewer',
			'post_id'                         => (int) $post_id,
			'post_type'                       => $post->post_type,
			'reviewer_user_id'                => self::$admin_user_id,
			'low_privileged_saver_user_id'    => self::$admin_user_id + 1,
			'server_version'                  => (string) $server_version,
			'proposed_post_content_hash'      => hash( 'sha256', $proposed_content ),
			'reviewed_proposed_content_hash'  => hash( 'sha256', $proposed_content ),
			'candidate_post_content_hash'     => $candidate_hash,
			'reviewed_candidate_content_hash' => $candidate_hash,
			'reviewed_block_items'            => array(
				array(
					'id'                             => 'risky-html-' . sanitize_key( $label ),
					'block_name'                     => 'core/html',
					'change_kind'                    => 'added_block',
					'risk_reason'                    => 'kses_would_remove_script',
					'proposed_content_hash'          => hash( 'sha256', $proposed_content ),
					'reviewed_proposed_content_hash' => hash( 'sha256', $proposed_content ),
					'review_status'                  => 'approved_for_retry_save',
					'review_evidence_type'           => 'kses_block_hash_only_change',
					'content_review_policy'          => 'kses',
				),
			),
			'proof_signature'                  => $proof_signature,
			'raw_content_included'             => false,
			'issued_at'                        => $issued_at,
			'expires_at'                       => $issued_at + HOUR_IN_SECONDS,
		);
		$envelope         = wp_de_rtc_create_opaque_review_approval_proof_token_envelope( $proof );

		$this->assertNotWPError( $envelope );
		$this->assertIsArray( $envelope );
		$this->audit_option_names[] = wp_de_rtc_get_opaque_review_approval_proof_token_audit_option_name( $envelope['token'] );
		$this->transient_keys[]     = wp_de_rtc_get_opaque_review_approval_proof_token_transient_key_from_envelope( $envelope );

		return array(
			'envelope'         => $envelope,
			'proof_signature'  => $proof_signature,
			'proposed_content' => $proposed_content,
		);
	}

	private function make_audit_record_cleanup_eligible( $envelope, $now ) {
		$record      = wp_de_rtc_get_opaque_review_approval_proof_token_audit_record( $envelope );
		$option_name = wp_de_rtc_get_opaque_review_approval_proof_token_audit_option_name( $envelope['token'] );

		$this->assertIsArray( $record );

		$old_time                             = $now - wp_de_rtc_get_opaque_review_approval_proof_token_audit_retention_seconds() - HOUR_IN_SECONDS;
		$record['created_at']                = $old_time;
		$record['updated_at']                = $old_time;
		$record['issued_at']                 = $old_time;
		$record['expires_at']                = $old_time;
		$record['minted_at']                 = $old_time;
		$record['audit_cleanup_eligible_at'] = $now - MINUTE_IN_SECONDS;

		update_option( $option_name, $record, false );

		return $record;
	}

	private function make_audit_record_with_stale_stored_cleanup_time( $envelope, $now ) {
		$record      = wp_de_rtc_get_opaque_review_approval_proof_token_audit_record( $envelope );
		$option_name = wp_de_rtc_get_opaque_review_approval_proof_token_audit_option_name( $envelope['token'] );

		$this->assertIsArray( $record );

		$record['audit_cleanup_eligible_at'] = $now - MINUTE_IN_SECONDS;

		update_option( $option_name, $record, false );

		return $record;
	}

	private function get_post_revisions( $post_id ) {
		return wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
	}

	private function assert_audit_payload_omits_private_fields( $payload, $forbidden_fragments ) {
		$encoded_payload = wp_json_encode( $payload );

		$this->assertIsString( $encoded_payload );

		foreach ( $forbidden_fragments as $forbidden_fragment ) {
			$this->assertStringNotContainsString( $forbidden_fragment, $encoded_payload );
		}

		$this->assert_payload_omits_keys(
			$payload,
			array(
				'token',
				'field_based_review_approval_proof',
				'proof',
				'proof_signature',
				'reviewed_block_items',
				'reviewer_user_id',
				'low_privileged_saver_user_id',
				'raw_content',
				'raw_post_content',
				'proposed_post_content',
				'candidate_post_content',
			)
		);
	}

	private function assert_payload_omits_keys( $payload, $forbidden_keys ) {
		if ( is_object( $payload ) ) {
			$payload = get_object_vars( $payload );
		}

		if ( ! is_array( $payload ) ) {
			return;
		}

		foreach ( $payload as $key => $value ) {
			if ( is_string( $key ) ) {
				$this->assertNotContains( $key, $forbidden_keys );
			}

			$this->assert_payload_omits_keys( $value, $forbidden_keys );
		}
	}

	private function assert_post_unchanged( $post_id, $before_content, $before_revisions ) {
		$after_post      = get_post( $post_id );
		$after_revisions = $this->get_post_revisions( $post_id );

		$this->assertSame( $before_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}
}
