<?php
/**
 * Tests for Distributed Editing block identity validators.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 */

class Tests_DE_RTC_Block_Identity extends WP_UnitTestCase {

	/**
	 * @covers ::wp_de_rtc_get_block_identity_contract_required_fields
	 * @covers ::wp_de_rtc_validate_block_identity_sync_meta_contract
	 * @covers ::wp_de_rtc_validate_block_identity_request_proof
	 */
	public function test_validates_complete_sync_meta_and_request_proof_without_write_claims() {
		$sync_meta       = $this->get_valid_block_identity_sync_meta();
		$sync_validation = wp_de_rtc_validate_block_identity_sync_meta_contract( $sync_meta );

		$this->assertIsArray( $sync_validation );
		$this->assertSame( 'valid', $sync_validation['status'] );
		$this->assertNull( $sync_validation['detail'] );
		$this->assertSame( 'de-rtc-block-identity-v1', $sync_validation['schema'] );
		$this->assertSame( 'doc-turn-0365', $sync_validation['document_uuid'] );
		$this->assertSame( '41', $sync_validation['version'] );
		$this->assertSame( 2, $sync_validation['block_count'] );
		$this->assertSame( array( 'block-a', 'block-b' ), $sync_validation['block_uids'] );
		$this->assertContains( 'proposed_block_map', $sync_validation['required_fields']['request_proof'] );
		$this->assert_no_write_evidence( $sync_validation );

		$request_proof    = $this->get_valid_block_identity_request_proof();
		$proof_validation = wp_de_rtc_validate_block_identity_request_proof( $request_proof, $sync_meta );

		$this->assertIsArray( $proof_validation );
		$this->assertSame( 'valid', $proof_validation['status'] );
		$this->assertNull( $proof_validation['detail'] );
		$this->assertSame( '41', $proof_validation['client_base_version'] );
		$this->assertSame( $request_proof['proposed_post_content_hash'], $proof_validation['proposed_post_content_hash'] );
		$this->assertSame( 3, $proof_validation['proposed_block_count'] );
		$this->assertSame( 2, $proof_validation['retained_block_count'] );
		$this->assertSame( 1, $proof_validation['inserted_block_count'] );
		$this->assertSame( 0, $proof_validation['deleted_block_count'] );
		$this->assertSame( 0, $proof_validation['moved_block_count'] );
		$this->assert_no_write_evidence( $proof_validation );
	}

	/**
	 * @covers ::wp_de_rtc_validate_block_identity_sync_meta_contract
	 */
	public function test_rejects_missing_sync_meta_required_field() {
		$sync_meta = $this->get_valid_block_identity_sync_meta();
		unset( $sync_meta['content_hash'] );

		$result = wp_de_rtc_validate_block_identity_sync_meta_contract( $sync_meta );

		$this->assertWPError( $result );
		$this->assertSame( 'de_rtc_malformed_sync_payload', $result->get_error_code() );
		$this->assertSame( 'block_identity_sync_meta_missing_required_field', $result->get_error_data()['detail'] );
		$this->assertSame( 'content_hash', $result->get_error_data()['missing_field'] );
		$this->assert_no_write_evidence( $result->get_error_data() );
	}

	/**
	 * @covers ::wp_de_rtc_validate_block_identity_sync_meta_contract
	 * @covers ::wp_de_rtc_find_raw_post_content_param_paths
	 */
	public function test_rejects_raw_content_fields_from_sync_meta() {
		$sync_meta                    = $this->get_valid_block_identity_sync_meta();
		$sync_meta['rawPostContent'] = '<!-- wp:paragraph --><p>Raw content must not ride inside identity proof.</p><!-- /wp:paragraph -->';

		$result = wp_de_rtc_validate_block_identity_sync_meta_contract( $sync_meta );

		$this->assertWPError( $result );
		$this->assertSame( 'block_identity_raw_content_rejected', $result->get_error_data()['detail'] );
		$this->assertContains( 'rawPostContent', $result->get_error_data()['raw_content_param_paths'] );
		$this->assert_no_write_evidence( $result->get_error_data() );
	}

	/**
	 * @covers ::wp_de_rtc_validate_block_identity_sync_meta_contract
	 * @covers ::wp_de_rtc_find_gutenberg_client_id_param_paths
	 */
	public function test_rejects_gutenberg_client_id_from_sync_meta() {
		$sync_meta                          = $this->get_valid_block_identity_sync_meta();
		$sync_meta['blocks'][0]['clientId'] = 'transient-editor-client-id';

		$result = wp_de_rtc_validate_block_identity_sync_meta_contract( $sync_meta );

		$this->assertWPError( $result );
		$this->assertSame( 'block_identity_client_id_rejected', $result->get_error_data()['detail'] );
		$this->assertContains( 'blocks.0.clientId', $result->get_error_data()['client_id_param_paths'] );
		$this->assert_no_write_evidence( $result->get_error_data() );
	}

	/**
	 * @covers ::wp_de_rtc_validate_block_identity_request_proof
	 */
	public function test_rejects_request_proof_base_version_mismatch() {
		$sync_meta     = $this->get_valid_block_identity_sync_meta();
		$request_proof = $this->get_valid_block_identity_request_proof();
		$request_proof['client_base_version'] = '40';

		$result = wp_de_rtc_validate_block_identity_request_proof( $request_proof, $sync_meta );

		$this->assertWPError( $result );
		$this->assertSame( 'block_identity_request_proof_base_version_mismatch', $result->get_error_data()['detail'] );
		$this->assertSame( '40', $result->get_error_data()['client_base_version'] );
		$this->assertSame( '41', $result->get_error_data()['server_version'] );
		$this->assert_no_write_evidence( $result->get_error_data() );
	}

	/**
	 * @covers ::wp_de_rtc_validate_block_identity_request_proof
	 * @covers ::wp_de_rtc_validate_block_identity_uid_list
	 */
	public function test_rejects_request_proof_unknown_block_uid() {
		$sync_meta     = $this->get_valid_block_identity_sync_meta();
		$request_proof = $this->get_valid_block_identity_request_proof();

		$request_proof['retained_block_uids'][1]             = 'block-missing';
		$request_proof['proposed_block_map'][2]['block_uid'] = 'block-missing';

		$result = wp_de_rtc_validate_block_identity_request_proof( $request_proof, $sync_meta );

		$this->assertWPError( $result );
		$this->assertSame( 'block_identity_request_proof_unknown_block_uid', $result->get_error_data()['detail'] );
		$this->assertSame( 'retained_block_uids', $result->get_error_data()['field'] );
		$this->assertSame( 'block-missing', $result->get_error_data()['unknown_block_uid'] );
		$this->assert_no_write_evidence( $result->get_error_data() );
	}

	/**
	 * @covers ::wp_de_rtc_validate_block_identity_sync_meta_contract
	 * @covers ::wp_de_rtc_validate_block_identity_request_proof
	 */
	public function test_validators_do_not_mutate_post_content_or_create_revisions() {
		$post_content = '<!-- wp:paragraph --><p>Unchanged authority content.</p><!-- /wp:paragraph -->';
		$post_id      = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC block identity no write post',
				'post_content' => $post_content,
			)
		);
		$revisions    = wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
		$sync_meta    = $this->get_valid_block_identity_sync_meta();
		$proof        = $this->get_valid_block_identity_request_proof();

		$sync_meta['blocks'][0]['clientId'] = 'transient-editor-client-id';
		$proof['client_base_version']       = '40';

		wp_de_rtc_validate_block_identity_sync_meta_contract( $sync_meta );
		wp_de_rtc_validate_block_identity_request_proof( $proof, $this->get_valid_block_identity_sync_meta() );

		$this->assertSame( $post_content, get_post( $post_id )->post_content );
		$this->assertSame(
			array_map( 'intval', array_keys( $revisions ) ),
			array_map(
				'intval',
				array_keys(
					wp_get_post_revisions(
						$post_id,
						array(
							'check_enabled' => false,
						)
					)
				)
			)
		);
	}

	private function get_valid_block_identity_sync_meta() {
		return array(
			'schema'        => 'de-rtc-block-identity-v1',
			'document_uuid' => 'doc-turn-0365',
			'version'       => '41',
			'content_hash'  => hash( 'sha256', 'stripped-post-content' ),
			'blocks'        => array(
				array(
					'block_uid'       => 'block-a',
					'parent_uid'      => null,
					'block_name'      => 'core/paragraph',
					'ordinal_path'    => array( 0 ),
					'serialized_hash' => hash( 'sha256', 'serialized-block-a' ),
				),
				array(
					'block_uid'       => 'block-b',
					'parent_uid'      => null,
					'block_name'      => 'core/paragraph',
					'ordinal_path'    => array( 1 ),
					'serialized_hash' => hash( 'sha256', 'serialized-block-b' ),
				),
			),
		);
	}

	private function get_valid_block_identity_request_proof() {
		return array(
			'client_base_version'        => '41',
			'proposed_post_content_hash' => hash( 'sha256', 'proposed-stripped-post-content' ),
			'proposed_block_map'         => array(
				array(
					'block_uid'       => 'block-a',
					'block_name'      => 'core/paragraph',
					'ordinal_path'    => array( 0 ),
					'serialized_hash' => hash( 'sha256', 'serialized-block-a' ),
				),
				array(
					'inserted_block_nonce' => 'insert-1',
					'block_name'           => 'core/paragraph',
					'ordinal_path'         => array( 1 ),
					'serialized_hash'      => hash( 'sha256', 'serialized-block-c' ),
				),
				array(
					'block_uid'       => 'block-b',
					'block_name'      => 'core/paragraph',
					'ordinal_path'    => array( 2 ),
					'serialized_hash' => hash( 'sha256', 'serialized-block-b' ),
				),
			),
			'retained_block_uids'        => array( 'block-a', 'block-b' ),
			'inserted_block_nonces'      => array( 'insert-1' ),
			'deleted_block_uids'         => array(),
			'moved_block_uids'           => array(),
		);
	}

	private function assert_no_write_evidence( $data ) {
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['changes_post_lock'] );
		$this->assertFalse( $data['claims_saved'] );
	}
}
