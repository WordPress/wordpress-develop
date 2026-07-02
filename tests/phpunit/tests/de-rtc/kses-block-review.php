<?php
/**
 * Tests for Distributed Editing KSES block review classification.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 */

class Tests_DE_RTC_KSES_Block_Review extends WP_UnitTestCase {

	protected static $admin_user_id;
	protected static $author_user_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_user_id  = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$author_user_id = $factory->user->create( array( 'role' => 'author' ) );
	}

	/**
	 * @covers ::wp_de_rtc_classify_kses_risky_block_review_items
	 * @covers ::wp_de_rtc_get_kses_block_review_records
	 * @covers ::wp_de_rtc_get_kses_block_review_records_by_hash
	 * @covers ::wp_de_rtc_find_matching_kses_block_review_record_key
	 * @covers ::wp_de_rtc_collect_kses_block_review_records
	 * @covers ::wp_de_rtc_create_kses_block_review_item
	 * @covers ::wp_de_rtc_get_kses_block_review_label
	 * @covers ::wp_de_rtc_get_kses_block_review_risk_reason
	 * @covers ::wp_de_rtc_get_kses_post_content_review_evidence
	 */
	public function test_classifies_added_risky_block_as_hash_only_review_item_without_writing() {
		$post_id          = $this->create_sync_meta_post(
			'<!-- wp:paragraph --><p>Base paragraph.</p><!-- /wp:paragraph -->',
			21,
			self::$author_user_id
		);
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_content = '<!-- wp:html --><script>alert("blocked")</script><!-- /wp:html -->';
		$filtered_block   = wp_unslash( wp_filter_post_kses( wp_slash( $proposed_content ) ) );

		wp_set_current_user( self::$author_user_id );

		$result = wp_de_rtc_classify_kses_risky_block_review_items(
			$post_id,
			$proposed_content,
			array(
				'client_base_version' => '21',
				'author_id'           => self::$author_user_id,
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'block_review_required', $result['result'] );
		$this->assertSame( 'de_rtc_unfiltered_html_would_change_content', $result['reason_code'] );
		$this->assertSame( $post_id, $result['post_id'] );
		$this->assertSame( 'posts', $result['rest_base'] );
		$this->assertFalse( $result['user_can_unfiltered_html'] );
		$this->assertSame( 'unfiltered_html', $result['required_capability'] );
		$this->assertSame( 'kses', $result['content_review_policy'] );
		$this->assertSame( 'kses_block_hash_only_change', $result['review_evidence_type'] );
		$this->assertSame( '21', $result['server_version'] );
		$this->assertSame( '21', $result['client_base_version'] );
		$this->assertCount( 1, $result['review_items'] );
		$this->assertSame( 1, $result['review_item_count'] );
		$this->assertSame( 1, $result['pending_review_item_count'] );
		$this->assertTrue( $result['pre_publish_review_required'] );
		$this->assertSame( 'open_pre_publish_review', $result['save_action'] );
		$this->assertFalse( $result['raw_content_included'] );
		$this->assertFalse( $result['exposes_raw_content'] );
		$this->assertFalse( $result['saves_post'] );
		$this->assertFalse( $result['mutates_post_content'] );
		$this->assertFalse( $result['creates_revision'] );
		$this->assertFalse( $result['claims_saved'] );

		$item = $result['review_items'][0];

		$this->assertStringStartsWith( 'kses-review-', $item['id'] );
		$this->assertSame( 'server-block-0', $item['block_client_id'] );
		$this->assertSame( 'core/html', $item['block_name'] );
		$this->assertSame( 'HTML', $item['block_label'] );
		$this->assertSame( array( 0 ), $item['block_path'] );
		$this->assertSame( 'modified_block', $item['change_kind'] );
		$this->assertSame( 'kses_would_remove_script', $item['risk_reason'] );
		$this->assertSame( self::$author_user_id, $item['author_id'] );
		$this->assertSame( '21', $item['base_version'] );
		$this->assertSame( '21', $item['server_version'] );
		$this->assertSame( hash( 'sha256', '<!-- wp:paragraph --><p>Base paragraph.</p><!-- /wp:paragraph -->' ), $item['base_content_hash'] );
		$this->assertSame( hash( 'sha256', $proposed_content ), $item['proposed_content_hash'] );
		$this->assertSame( hash( 'sha256', $filtered_block ), $item['kses_filtered_content_hash'] );
		$this->assertSame( 'pending_review', $item['review_status'] );
		$this->assertSame( 'kses_block_hash_only_change', $item['review_evidence_type'] );
		$this->assertSame( 'kses', $item['content_review_policy'] );
		$this->assertFalse( $item['raw_content_included'] );
		$this->assertFalse( $item['exposes_raw_content'] );
		$this->assert_review_classification_omits_raw_content(
			$result,
			array( 'blocked', 'wp:html', 'Base paragraph' )
		);
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_classify_kses_risky_block_review_items
	 */
	public function test_privileged_user_does_not_create_risky_block_review_items() {
		$post_id          = $this->create_sync_meta_post(
			'<!-- wp:paragraph --><p>Privileged base paragraph.</p><!-- /wp:paragraph -->',
			22,
			self::$admin_user_id
		);
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$proposed_content = '<!-- wp:html --><script>alert("allowed for admin")</script><!-- /wp:html -->';

		wp_set_current_user( self::$admin_user_id );

		$result = wp_de_rtc_classify_kses_risky_block_review_items( $post_id, $proposed_content );

		$this->assertIsArray( $result );
		$this->assertSame( 'no_review_required', $result['result'] );
		$this->assertNull( $result['reason_code'] );
		$this->assertTrue( $result['user_can_unfiltered_html'] );
		$this->assertSame( 'continue_save', $result['save_action'] );
		$this->assertSame( array(), $result['review_items'] );
		$this->assertSame( 0, $result['review_item_count'] );
		$this->assertSame( 0, $result['pending_review_item_count'] );
		$this->assertFalse( $result['pre_publish_review_required'] );
		$this->assertFalse( $result['saves_post'] );
		$this->assertFalse( $result['mutates_post_content'] );
		$this->assertFalse( $result['creates_revision'] );
		$this->assertFalse( $result['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_classify_kses_risky_block_review_items
	 */
	public function test_unchanged_risky_block_is_not_reviewed_when_safe_block_changes() {
		$risky_block      = '<!-- wp:html --><script>alert("protected")</script><!-- /wp:html -->';
		$base_content     = $risky_block . "\n" . '<!-- wp:paragraph --><p>Original safe paragraph.</p><!-- /wp:paragraph -->';
		$proposed_content = $risky_block . "\n" . '<!-- wp:paragraph --><p>Updated safe paragraph.</p><!-- /wp:paragraph -->';
		$post_id          = $this->create_sync_meta_post( $base_content, 23, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );

		wp_set_current_user( self::$author_user_id );

		$result = wp_de_rtc_classify_kses_risky_block_review_items( $post_id, $proposed_content );

		$this->assertIsArray( $result );
		$this->assertSame( 'no_review_required', $result['result'] );
		$this->assertSame( 0, $result['review_item_count'] );
		$this->assertSame( 'continue_save', $result['save_action'] );
		$this->assertFalse( $result['pre_publish_review_required'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_classify_kses_risky_block_review_items
	 * @covers ::wp_de_rtc_modified_kses_block_change_requires_review
	 * @covers ::wp_de_rtc_get_kses_removed_fragment_signature
	 */
	public function test_safe_text_edit_in_paragraph_with_unchanged_kses_normalized_markup_does_not_require_review() {
		$base_content     = '<!-- wp:paragraph --><p>Original text with <mark style="background-color:rgba(0, 0, 0, 0)" class="has-inline-color has-accent-3-color">expression</mark>.</p><!-- /wp:paragraph -->';
		$proposed_content = '<!-- wp:paragraph --><p>Updated text with <mark style="background-color:rgba(0, 0, 0, 0)" class="has-inline-color has-accent-3-color">expression</mark>.</p><!-- /wp:paragraph -->';
		$post_id          = $this->create_sync_meta_post( $base_content, 29, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$base_review      = wp_de_rtc_get_kses_post_content_review_evidence( $base_content );
		$proposed_review  = wp_de_rtc_get_kses_post_content_review_evidence( $proposed_content );

		wp_set_current_user( self::$author_user_id );

		$result = wp_de_rtc_classify_kses_risky_block_review_items(
			$post_id,
			$proposed_content,
			array(
				'base_post_content'   => $base_content,
				'client_base_version' => '29',
				'author_id'           => self::$author_user_id,
			)
		);

		$this->assertTrue( $base_review['would_change_content'] );
		$this->assertTrue( $proposed_review['would_change_content'] );
		$this->assertIsArray( $result );
		$this->assertSame( 'no_review_required', $result['result'] );
		$this->assertSame( 0, $result['review_item_count'] );
		$this->assertSame( 'continue_save', $result['save_action'] );
		$this->assertFalse( $result['pre_publish_review_required'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_classify_kses_risky_block_review_items
	 * @covers ::wp_de_rtc_modified_kses_block_change_requires_review
	 * @covers ::wp_de_rtc_get_kses_sensitive_element_hashes
	 */
	public function test_changed_script_content_in_existing_html_block_still_requires_review() {
		$base_content     = '<!-- wp:html --><script>alert(1);</script>Script<!-- /wp:html -->';
		$proposed_content = '<!-- wp:html --><script>alert(2);</script>Script<!-- /wp:html -->';
		$post_id          = $this->create_sync_meta_post( $base_content, 30, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );

		wp_set_current_user( self::$author_user_id );

		$result = wp_de_rtc_classify_kses_risky_block_review_items(
			$post_id,
			$proposed_content,
			array(
				'base_post_content'   => $base_content,
				'client_base_version' => '30',
				'author_id'           => self::$author_user_id,
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'block_review_required', $result['result'] );
		$this->assertSame( 1, $result['review_item_count'] );
		$this->assertSame( 'core/html', $result['review_items'][0]['block_name'] );
		$this->assertSame( 'modified_block', $result['review_items'][0]['change_kind'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_classify_kses_risky_block_review_items
	 * @covers ::wp_de_rtc_find_matching_kses_block_review_record_key
	 */
	public function test_inserted_safe_block_before_unchanged_risky_block_does_not_require_review() {
		$risky_block      = '<!-- wp:html --><script>alert("protected")</script><!-- /wp:html -->';
		$safe_block       = '<!-- wp:paragraph --><p>Existing safe paragraph.</p><!-- /wp:paragraph -->';
		$inserted_block   = '<!-- wp:paragraph --><p>Inserted safe paragraph.</p><!-- /wp:paragraph -->';
		$base_content     = $risky_block . "\n" . $safe_block;
		$proposed_content = $inserted_block . "\n" . $risky_block . "\n" . $safe_block;
		$post_id          = $this->create_sync_meta_post( $base_content, 24, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );

		wp_set_current_user( self::$author_user_id );

		$result = wp_de_rtc_classify_kses_risky_block_review_items( $post_id, $proposed_content );

		$this->assertIsArray( $result );
		$this->assertSame( 'no_review_required', $result['result'] );
		$this->assertSame( 0, $result['review_item_count'] );
		$this->assertSame( 'continue_save', $result['save_action'] );
		$this->assertFalse( $result['pre_publish_review_required'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_classify_kses_risky_block_review_items
	 * @covers ::wp_de_rtc_added_kses_block_change_requires_review
	 * @covers ::wp_de_rtc_is_kses_normalization_only_change
	 * @covers ::wp_de_rtc_canonicalize_kses_normalized_serialized_html
	 */
	public function test_inserted_safe_separator_normalized_by_kses_does_not_require_review() {
		$base_content     = '<!-- wp:paragraph --><p>Existing safe paragraph.</p><!-- /wp:paragraph -->';
		$separator_block  = '<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->';
		$proposed_content = $base_content . "\n" . $separator_block;
		$post_id          = $this->create_sync_meta_post( $base_content, 31, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );
		$separator_review = wp_de_rtc_get_kses_post_content_review_evidence( $separator_block );

		wp_set_current_user( self::$author_user_id );

		$result = wp_de_rtc_classify_kses_risky_block_review_items(
			$post_id,
			$proposed_content,
			array(
				'base_post_content'   => $base_content,
				'client_base_version' => '31',
				'author_id'           => self::$author_user_id,
			)
		);

		$this->assertTrue( $separator_review['would_change_content'] );
		$this->assertIsArray( $result );
		$this->assertSame( 'no_review_required', $result['result'] );
		$this->assertSame( 0, $result['review_item_count'] );
		$this->assertSame( 'continue_save', $result['save_action'] );
		$this->assertFalse( $result['pre_publish_review_required'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_classify_kses_risky_block_review_items
	 * @covers ::wp_de_rtc_added_kses_block_change_requires_review
	 */
	public function test_inserted_common_safe_blocks_do_not_require_review() {
		$base_content = '<!-- wp:paragraph --><p>Existing safe paragraph.</p><!-- /wp:paragraph -->';
		$safe_blocks  = array(
			'heading' => '<!-- wp:heading --><h2>Safe heading</h2><!-- /wp:heading -->',
			'list'    => '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>One</li><!-- /wp:list-item --><!-- wp:list-item --><li>Two</li><!-- /wp:list-item --></ul><!-- /wp:list -->',
			'spacer'  => '<!-- wp:spacer --><div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->',
			'quote'   => '<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>Quoted</p><!-- /wp:paragraph --></blockquote><!-- /wp:quote -->',
			'button'  => '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Button</a></div><!-- /wp:button --></div><!-- /wp:buttons -->',
		);

		wp_set_current_user( self::$author_user_id );

		foreach ( $safe_blocks as $label => $safe_block ) {
			$post_id          = $this->create_sync_meta_post( $base_content, 32, self::$author_user_id );
			$before_post      = get_post( $post_id );
			$before_revisions = $this->get_post_revisions( $post_id );
			$proposed_content = $base_content . "\n" . $safe_block;
			$result           = wp_de_rtc_classify_kses_risky_block_review_items(
				$post_id,
				$proposed_content,
				array(
					'base_post_content'   => $base_content,
					'client_base_version' => '32',
					'author_id'           => self::$author_user_id,
				)
			);

			$this->assertIsArray( $result, $label );
			$this->assertSame( 'no_review_required', $result['result'], $label );
			$this->assertSame( 0, $result['review_item_count'], $label );
			$this->assertSame( 'continue_save', $result['save_action'], $label );
			$this->assertFalse( $result['pre_publish_review_required'], $label );
			$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
		}
	}

	/**
	 * @covers ::wp_de_rtc_classify_kses_risky_block_review_items
	 * @covers ::wp_de_rtc_added_kses_block_change_requires_review
	 */
	public function test_inserted_block_with_event_handler_still_requires_review() {
		$base_content     = '<!-- wp:paragraph --><p>Existing safe paragraph.</p><!-- /wp:paragraph -->';
		$unsafe_block     = '<!-- wp:paragraph --><p onclick="alert(1)">Unsafe event handler.</p><!-- /wp:paragraph -->';
		$proposed_content = $base_content . "\n" . $unsafe_block;
		$post_id          = $this->create_sync_meta_post( $base_content, 33, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );

		wp_set_current_user( self::$author_user_id );

		$result = wp_de_rtc_classify_kses_risky_block_review_items(
			$post_id,
			$proposed_content,
			array(
				'base_post_content'   => $base_content,
				'client_base_version' => '33',
				'author_id'           => self::$author_user_id,
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'block_review_required', $result['result'] );
		$this->assertSame( 1, $result['review_item_count'] );
		$this->assertSame( 'core/paragraph', $result['review_items'][0]['block_name'] );
		$this->assertSame( 'added_block', $result['review_items'][0]['change_kind'] );
		$this->assertSame( 'kses_would_alter_attributes', $result['review_items'][0]['risk_reason'] );
		$this->assert_review_classification_omits_raw_content(
			$result,
			array( 'Unsafe event handler', 'onclick', 'alert' )
		);
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_classify_kses_risky_block_review_items
	 */
	public function test_deleted_risky_block_does_not_require_review() {
		$risky_block      = '<!-- wp:html --><script>alert("protected")</script><!-- /wp:html -->';
		$base_content     = $risky_block . "\n" . '<!-- wp:paragraph --><p>Keep this paragraph.</p><!-- /wp:paragraph -->';
		$proposed_content = '<!-- wp:paragraph --><p>Keep this paragraph.</p><!-- /wp:paragraph -->';
		$post_id          = $this->create_sync_meta_post( $base_content, 25, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );

		wp_set_current_user( self::$author_user_id );

		$result = wp_de_rtc_classify_kses_risky_block_review_items( $post_id, $proposed_content );

		$this->assertIsArray( $result );
		$this->assertSame( 'no_review_required', $result['result'] );
		$this->assertNull( $result['reason_code'] );
		$this->assertSame( 0, $result['review_item_count'] );
		$this->assertSame( 0, $result['pending_review_item_count'] );
		$this->assertSame( array(), $result['review_items'] );
		$this->assertSame( 'continue_save', $result['save_action'] );
		$this->assertFalse( $result['pre_publish_review_required'] );
		$this->assert_review_classification_omits_raw_content(
			$result,
			array( 'protected', 'wp:html', 'Keep this paragraph' )
		);
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_classify_kses_risky_block_review_items
	 * @covers ::wp_de_rtc_collect_kses_block_review_records
	 * @covers ::wp_de_rtc_get_block_without_inner_blocks
	 */
	public function test_deleted_nested_risky_block_does_not_require_review() {
		$risky_block      = '<!-- wp:html --><script>alert("nested-delete")</script>Script<!-- /wp:html -->';
		$first_paragraph  = '<!-- wp:paragraph --><p>First nested paragraph.</p><!-- /wp:paragraph -->';
		$second_paragraph = '<!-- wp:paragraph --><p>Second nested paragraph.</p><!-- /wp:paragraph -->';
		$base_content     = '<!-- wp:group --><div class="wp-block-group"><!-- wp:quote --><blockquote class="wp-block-quote">' . $first_paragraph . $risky_block . $second_paragraph . '</blockquote><!-- /wp:quote --></div><!-- /wp:group -->';
		$proposed_content = '<!-- wp:group --><div class="wp-block-group"><!-- wp:quote --><blockquote class="wp-block-quote">' . $first_paragraph . $second_paragraph . '</blockquote><!-- /wp:quote --></div><!-- /wp:group -->';
		$post_id          = $this->create_sync_meta_post( $base_content, 34, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );

		wp_set_current_user( self::$author_user_id );

		$result = wp_de_rtc_classify_kses_risky_block_review_items(
			$post_id,
			$proposed_content,
			array(
				'base_post_content'   => $base_content,
				'client_base_version' => '34',
				'author_id'           => self::$author_user_id,
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'no_review_required', $result['result'] );
		$this->assertSame( 0, $result['review_item_count'] );
		$this->assertSame( array(), $result['review_items'] );
		$this->assertSame( 'continue_save', $result['save_action'] );
		$this->assertFalse( $result['pre_publish_review_required'] );
		$this->assert_review_classification_omits_raw_content(
			$result,
			array( 'nested-delete', 'Script', 'wp:html' )
		);
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_classify_kses_risky_block_review_items
	 * @covers ::wp_de_rtc_added_kses_block_change_requires_review
	 * @covers ::wp_de_rtc_get_kses_block_review_risk_reason
	 */
	public function test_deleted_risky_block_with_added_risky_block_reviews_only_added_block() {
		$deleted_risky_block = '<!-- wp:html --><script>alert("deleted-risky")</script><!-- /wp:html -->';
		$kept_paragraph      = '<!-- wp:paragraph --><p>Keep this paragraph.</p><!-- /wp:paragraph -->';
		$added_risky_block   = '<!-- wp:html --><script>alert("added-risky")</script><!-- /wp:html -->';
		$base_content        = $deleted_risky_block . "\n" . $kept_paragraph;
		$proposed_content    = $kept_paragraph . "\n" . $added_risky_block;
		$post_id             = $this->create_sync_meta_post( $base_content, 35, self::$author_user_id );
		$before_post         = get_post( $post_id );
		$before_revisions    = $this->get_post_revisions( $post_id );

		wp_set_current_user( self::$author_user_id );

		$result = wp_de_rtc_classify_kses_risky_block_review_items(
			$post_id,
			$proposed_content,
			array(
				'base_post_content'   => $base_content,
				'client_base_version' => '35',
				'author_id'           => self::$author_user_id,
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'block_review_required', $result['result'] );
		$this->assertSame( 1, $result['review_item_count'] );
		$this->assertSame( 'open_pre_publish_review', $result['save_action'] );

		$item = $result['review_items'][0];

		$this->assertSame( 'added_block', $item['change_kind'] );
		$this->assertSame( 'core/html', $item['block_name'] );
		$this->assertSame( array( 1 ), $item['block_path'] );
		$this->assertSame( 'kses_would_remove_script', $item['risk_reason'] );
		$this->assertSame( hash( 'sha256', '' ), $item['base_content_hash'] );
		$this->assertSame( hash( 'sha256', $added_risky_block ), $item['proposed_content_hash'] );
		$this->assert_review_classification_omits_raw_content(
			$result,
			array( 'deleted-risky', 'added-risky', 'wp:html', 'Keep this paragraph' )
		);
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_classify_kses_risky_block_review_items
	 * @covers ::wp_de_rtc_collect_kses_block_review_records
	 * @covers ::wp_de_rtc_get_block_without_inner_blocks
	 */
	public function test_nested_risky_html_flags_only_immediate_offending_block() {
		$safe_quote       = '<!-- wp:group --><div class="wp-block-group"><!-- wp:quote --><blockquote class="wp-block-quote"><p>Safe quote.</p></blockquote><!-- /wp:quote --></div><!-- /wp:group -->';
		$risky_quote      = '<!-- wp:group --><div class="wp-block-group"><!-- wp:quote --><blockquote class="wp-block-quote"><p>Safe quote.</p><!-- wp:html --><script>alert("nested")</script>Script<!-- /wp:html --></blockquote><!-- /wp:quote --></div><!-- /wp:group -->';
		$post_id          = $this->create_sync_meta_post( $safe_quote, 27, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );

		wp_set_current_user( self::$author_user_id );

		$result = wp_de_rtc_classify_kses_risky_block_review_items(
			$post_id,
			$risky_quote,
			array(
				'client_base_version' => '27',
				'author_id'           => self::$author_user_id,
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'block_review_required', $result['result'] );
		$this->assertSame( 1, $result['review_item_count'] );
		$this->assertCount( 1, $result['review_items'] );

		$item = $result['review_items'][0];

		$this->assertSame( 'core/html', $item['block_name'] );
		$this->assertSame( 'HTML', $item['block_label'] );
		$this->assertSame( array( 0, 0, 0 ), $item['block_path'] );
		$this->assertSame( 'added_block', $item['change_kind'] );
		$this->assertSame( hash( 'sha256', '' ), $item['base_content_hash'] );
		$this->assertSame( hash( 'sha256', '<!-- wp:html --><script>alert("nested")</script>Script<!-- /wp:html -->' ), $item['proposed_content_hash'] );
		$this->assert_review_classification_omits_raw_content(
			$result,
			array( 'nested', 'Script', 'wp:group', 'wp:quote', 'wp:html' )
		);
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_classify_kses_risky_block_review_items
	 * @covers ::wp_de_rtc_collect_kses_block_review_records
	 * @covers ::wp_de_rtc_get_block_without_inner_blocks
	 */
	public function test_nested_risky_html_before_retained_sibling_flags_only_immediate_block() {
		$safe_quote       = '<!-- wp:group --><div class="wp-block-group"><!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>First nested paragraph.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Second nested paragraph.</p><!-- /wp:paragraph --></blockquote><!-- /wp:quote --></div><!-- /wp:group -->';
		$unsafe_block     = '<!-- wp:html --><script>alert("nested-before")</script>Script<!-- /wp:html -->';
		$risky_quote      = '<!-- wp:group --><div class="wp-block-group"><!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>First nested paragraph.</p><!-- /wp:paragraph -->' . $unsafe_block . '<!-- wp:paragraph --><p>Second nested paragraph.</p><!-- /wp:paragraph --></blockquote><!-- /wp:quote --></div><!-- /wp:group -->';
		$post_id          = $this->create_sync_meta_post( $safe_quote, 28, self::$author_user_id );
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );

		wp_set_current_user( self::$author_user_id );

		$result = wp_de_rtc_classify_kses_risky_block_review_items(
			$post_id,
			$risky_quote,
			array(
				'client_base_version' => '28',
				'author_id'           => self::$author_user_id,
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'block_review_required', $result['result'] );
		$this->assertSame( 1, $result['review_item_count'] );
		$this->assertCount( 1, $result['review_items'] );

		$item = $result['review_items'][0];

		$this->assertSame( 'core/html', $item['block_name'] );
		$this->assertSame( array( 0, 0, 1 ), $item['block_path'] );
		$this->assertSame( 'added_block', $item['change_kind'] );
		$this->assertSame( hash( 'sha256', '' ), $item['base_content_hash'] );
		$this->assertSame( hash( 'sha256', $unsafe_block ), $item['proposed_content_hash'] );
		$this->assert_review_classification_omits_raw_content(
			$result,
			array( 'nested-before', 'Script', 'wp:group', 'wp:quote', 'wp:html' )
		);
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	/**
	 * @covers ::wp_de_rtc_classify_kses_risky_block_review_items
	 */
	public function test_missing_proposed_content_returns_malformed_no_write_error() {
		$post_id          = $this->create_sync_meta_post(
			'<!-- wp:paragraph --><p>Malformed classifier base.</p><!-- /wp:paragraph -->',
			26,
			self::$author_user_id
		);
		$before_post      = get_post( $post_id );
		$before_revisions = $this->get_post_revisions( $post_id );

		$error = wp_de_rtc_classify_kses_risky_block_review_items( $post_id, null );
		$data  = $error->get_error_data( 'de_rtc_malformed_sync_payload' );

		$this->assertWPError( $error );
		$this->assertSame( 'de_rtc_malformed_sync_payload', $error->get_error_code() );
		$this->assertSame( 'missing_kses_block_review_proposed_content', $data['detail'] );
		$this->assertFalse( $data['raw_content_included'] );
		$this->assertFalse( $data['saves_post'] );
		$this->assertFalse( $data['mutates_post_content'] );
		$this->assertFalse( $data['creates_revision'] );
		$this->assertFalse( $data['claims_saved'] );
		$this->assert_post_unchanged( $post_id, $before_post->post_content, $before_revisions );
	}

	private function create_sync_meta_post( $content, $version, $post_author ) {
		$content = wp_de_rtc_add_sync_meta_to_post_content(
			$content,
			'diff-match-patch',
			array(
				'version' => (string) $version,
			)
		);

		$this->assertIsString( $content );

		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'DE-RTC KSES block review post',
				'post_author'  => $post_author,
				'post_content' => '',
			)
		);

		global $wpdb;

		$updated = $wpdb->update(
			$wpdb->posts,
			array(
				'post_content' => $content,
			),
			array(
				'ID' => $post_id,
			)
		);

		$this->assertSame( 1, $updated );
		clean_post_cache( $post_id );

		return $post_id;
	}

	private function get_post_revisions( $post_id ) {
		return wp_get_post_revisions(
			$post_id,
			array(
				'check_enabled' => false,
			)
		);
	}

	private function assert_post_unchanged( $post_id, $before_content, $before_revisions ) {
		$after_post      = get_post( $post_id );
		$after_revisions = $this->get_post_revisions( $post_id );

		$this->assertSame( $before_content, $after_post->post_content );
		$this->assertSame( array_keys( $before_revisions ), array_keys( $after_revisions ) );
	}

	private function assert_review_classification_omits_raw_content( $classification, $raw_fragments ) {
		$encoded = wp_json_encode( $classification );

		$this->assertIsString( $encoded );

		foreach ( $raw_fragments as $fragment ) {
			$this->assertStringNotContainsString( $fragment, $encoded );
		}
	}
}
