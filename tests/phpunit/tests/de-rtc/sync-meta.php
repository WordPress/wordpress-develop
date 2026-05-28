<?php
/**
 * Tests for Distributed Editing sync-meta parse and format helpers.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group de-rtc
 */

require_once ABSPATH . WPINC . '/de-rtc.php';

class Tests_DE_RTC_Sync_Meta extends WP_UnitTestCase {

	/**
	 * @covers ::wp_de_rtc_format_sync_meta
	 * @covers ::wp_de_rtc_parse_post_content_sync_meta
	 */
	public function test_formats_sync_meta_without_literal_script_close_in_json() {
		$script = wp_de_rtc_format_sync_meta(
			'diff-match-patch',
			array(
				'version' => 1,
				'payload' => 'before </script><script>alert( 1 )</script> after',
			)
		);

		$this->assertIsString( $script );
		$this->assertStringContainsString( 'type="application/json"', $script );
		$this->assertStringContainsString( 'data-wp-sync-meta="distributed-editing"', $script );
		$this->assertStringContainsString( 'data-sync-meta-format="diff-match-patch"', $script );
		$this->assertStringContainsString( '\u003C/script\u003E', $script );

		preg_match( '/<script\b[^>]*>(.*)<\/script\s*>/is', $script, $matches );

		$this->assertArrayHasKey( 1, $matches );
		$this->assertStringNotContainsString( '</script', strtolower( trim( $matches[1] ) ) );
	}

	/**
	 * @covers ::wp_de_rtc_add_sync_meta_to_post_content
	 * @covers ::wp_de_rtc_parse_post_content_sync_meta
	 */
	public function test_parses_trailing_sync_meta_and_returns_content_without_meta() {
		$content   = '<!-- wp:paragraph --><p>Hello.</p><!-- /wp:paragraph -->';
		$metadata  = array(
			'baseVersion' => 7,
			'hash'        => 'abc123',
		);
		$combined  = wp_de_rtc_add_sync_meta_to_post_content( $content, 'diff-match-patch', $metadata, 'trailer' );
		$extracted = wp_de_rtc_parse_post_content_sync_meta( $combined );

		$this->assertIsArray( $extracted );
		$this->assertSame( $content, $extracted['content'] );
		$this->assertSame( $metadata, $extracted['sync_meta'] );
		$this->assertSame( 'diff-match-patch', $extracted['sync_meta_format'] );
		$this->assertSame( 'trailer', $extracted['sync_meta_position'] );
		$this->assertStringContainsString( '<script', $extracted['raw_sync_meta'] );
	}

	/**
	 * @covers ::wp_de_rtc_add_sync_meta_to_post_content
	 * @covers ::wp_de_rtc_parse_post_content_sync_meta
	 * @covers ::wp_de_rtc_match_edge_sync_meta_script
	 */
	public function test_parses_trailing_sync_meta_after_content_script_block() {
		$content   = '<!-- wp:html --><script>alert("existing")</script><!-- /wp:html -->'
			. "\n"
			. '<!-- wp:paragraph --><p>Hello.</p><!-- /wp:paragraph -->';
		$metadata  = array(
			'baseVersion' => 9,
			'hash'        => 'script-content-base',
		);
		$combined  = wp_de_rtc_add_sync_meta_to_post_content( $content, 'diff-match-patch', $metadata, 'trailer' );
		$extracted = wp_de_rtc_parse_post_content_sync_meta( $combined );

		$this->assertIsArray( $extracted );
		$this->assertSame( $content, $extracted['content'] );
		$this->assertSame( $metadata, $extracted['sync_meta'] );
		$this->assertSame( 'diff-match-patch', $extracted['sync_meta_format'] );
		$this->assertSame( 'trailer', $extracted['sync_meta_position'] );
		$this->assertStringContainsString( 'data-wp-sync-meta="distributed-editing"', $extracted['raw_sync_meta'] );
		$this->assertStringNotContainsString( 'alert("existing")', $extracted['raw_sync_meta'] );
	}

	/**
	 * @covers ::wp_de_rtc_parse_post_content_sync_meta
	 * @covers ::wp_de_rtc_match_edge_sync_meta_script
	 */
	public function test_parses_paragraph_wrapped_trailing_sync_meta_and_returns_content_without_meta() {
		$content  = '<!-- wp:paragraph --><p>Hello.</p><!-- /wp:paragraph -->';
		$metadata = array(
			'version' => '12',
			'hash'    => 'wrapped-trailer-base',
		);
		$script   = wp_de_rtc_format_sync_meta( 'diff-match-patch', $metadata );
		$combined = $content . "\n\n" . '<p>' . $script . '</p>';

		$extracted = wp_de_rtc_parse_post_content_sync_meta( $combined );

		$this->assertIsArray( $extracted );
		$this->assertSame( $content, $extracted['content'] );
		$this->assertSame( $metadata, $extracted['sync_meta'] );
		$this->assertSame( 'diff-match-patch', $extracted['sync_meta_format'] );
		$this->assertSame( 'trailer', $extracted['sync_meta_position'] );
		$this->assertStringContainsString( 'data-wp-sync-meta="distributed-editing"', $extracted['raw_sync_meta'] );
		$this->assertStringContainsString( 'wrapped-trailer-base', $extracted['raw_sync_meta'] );
	}

	/**
	 * @covers ::wp_de_rtc_parse_post_content_sync_meta
	 * @covers ::wp_de_rtc_match_edge_sync_meta_script
	 */
	public function test_parses_paragraph_wrapped_prefix_sync_meta_and_returns_content_without_meta() {
		$content  = '<!-- wp:paragraph --><p>Hello.</p><!-- /wp:paragraph -->';
		$metadata = array(
			'version' => '14',
			'hash'    => 'wrapped-prefix-paragraph-base',
		);
		$script   = wp_de_rtc_format_sync_meta( 'diff-match-patch', $metadata );
		$combined = '<p>' . $script . '</p>' . "\n\n" . $content;

		$extracted = wp_de_rtc_parse_post_content_sync_meta( $combined );

		$this->assertIsArray( $extracted );
		$this->assertSame( $content, $extracted['content'] );
		$this->assertSame( $metadata, $extracted['sync_meta'] );
		$this->assertSame( 'diff-match-patch', $extracted['sync_meta_format'] );
		$this->assertSame( 'prefix', $extracted['sync_meta_position'] );
		$this->assertStringContainsString( 'data-wp-sync-meta="distributed-editing"', $extracted['raw_sync_meta'] );
		$this->assertStringContainsString( 'wrapped-prefix-paragraph-base', $extracted['raw_sync_meta'] );
	}

	/**
	 * @covers ::wp_de_rtc_parse_post_content_sync_meta
	 * @covers ::wp_de_rtc_match_edge_sync_meta_script
	 */
	public function test_parses_freeform_wrapped_prefix_sync_meta_and_returns_content_without_meta() {
		$content  = '<!-- wp:paragraph --><p>Hello.</p><!-- /wp:paragraph -->';
		$metadata = array(
			'version' => '13',
			'hash'    => 'wrapped-prefix-base',
		);
		$script   = wp_de_rtc_format_sync_meta( 'diff-match-patch', $metadata );
		$combined = '<!-- wp:freeform --><p>' . $script . '</p><!-- /wp:freeform -->' . "\n\n" . $content;

		$extracted = wp_de_rtc_parse_post_content_sync_meta( $combined );

		$this->assertIsArray( $extracted );
		$this->assertSame( $content, $extracted['content'] );
		$this->assertSame( $metadata, $extracted['sync_meta'] );
		$this->assertSame( 'diff-match-patch', $extracted['sync_meta_format'] );
		$this->assertSame( 'prefix', $extracted['sync_meta_position'] );
		$this->assertStringContainsString( 'data-wp-sync-meta="distributed-editing"', $extracted['raw_sync_meta'] );
		$this->assertStringContainsString( 'wrapped-prefix-base', $extracted['raw_sync_meta'] );
	}

	/**
	 * @covers ::wp_de_rtc_add_sync_meta_to_post_content
	 * @covers ::wp_de_rtc_parse_post_content_sync_meta
	 * @covers ::wp_de_rtc_match_edge_sync_meta_script
	 */
	public function test_parses_core_sync_meta_prefix_block_and_returns_content_without_meta() {
		$content  = '<!-- wp:paragraph --><p>Hello.</p><!-- /wp:paragraph -->';
		$metadata = array(
			'version' => '20',
			'schema'  => 'de-rtc-yjs-v1',
		);
		$combined = wp_de_rtc_add_sync_meta_to_post_content( $content, 'yjs', $metadata, 'prefix-block' );

		$extracted = wp_de_rtc_parse_post_content_sync_meta( $combined );

		$this->assertIsArray( $extracted );
		$this->assertSame( $content, $extracted['content'] );
		$this->assertSame( $metadata, $extracted['sync_meta'] );
		$this->assertSame( 'yjs', $extracted['sync_meta_format'] );
		$this->assertSame( 'prefix-block', $extracted['sync_meta_position'] );
		$this->assertStringStartsWith( '<!-- wp:sync-meta', $combined );
		$this->assertStringContainsString( 'type="application/json"', $extracted['raw_sync_meta'] );
		$this->assertStringContainsString( 'data-wp-sync-meta="distributed-editing"', $extracted['raw_sync_meta'] );
	}

	/**
	 * @covers ::wp_de_rtc_parse_post_content_sync_meta
	 * @covers ::wp_de_rtc_match_edge_sync_meta_script
	 */
	public function test_parses_freeform_wrapped_trailing_sync_meta_and_returns_content_without_meta() {
		$content  = '<!-- wp:paragraph --><p>Hello.</p><!-- /wp:paragraph -->';
		$metadata = array(
			'version' => '15',
			'hash'    => 'wrapped-trailer-freeform-base',
		);
		$script   = wp_de_rtc_format_sync_meta( 'diff-match-patch', $metadata );
		$combined = $content . "\n\n" . '<!-- wp:freeform --><p>' . $script . '</p><!-- /wp:freeform -->';

		$extracted = wp_de_rtc_parse_post_content_sync_meta( $combined );

		$this->assertIsArray( $extracted );
		$this->assertSame( $content, $extracted['content'] );
		$this->assertSame( $metadata, $extracted['sync_meta'] );
		$this->assertSame( 'diff-match-patch', $extracted['sync_meta_format'] );
		$this->assertSame( 'trailer', $extracted['sync_meta_position'] );
		$this->assertStringContainsString( 'data-wp-sync-meta="distributed-editing"', $extracted['raw_sync_meta'] );
		$this->assertStringContainsString( 'wrapped-trailer-freeform-base', $extracted['raw_sync_meta'] );
	}

	/**
	 * @dataProvider data_sync_meta_edge_positions
	 *
	 * @covers ::wp_de_rtc_parse_post_content_sync_meta
	 * @covers ::wp_de_rtc_match_edge_sync_meta_script
	 */
	public function test_rejects_paragraph_wrapped_sync_meta_with_paragraph_attributes( $position ) {
		$content  = '<!-- wp:paragraph --><p>Hello.</p><!-- /wp:paragraph -->';
		$metadata = array(
			'version' => '16',
			'hash'    => 'wrapped-paragraph-attribute-base',
		);
		$script   = wp_de_rtc_format_sync_meta( 'diff-match-patch', $metadata );

		if ( 'prefix' === $position ) {
			$combined = '<p class="wp-block-html" data-origin="gutenberg">' . $script . '</p>' . "\n\n" . $content;
		} else {
			$combined = $content . "\n\n" . '<p class="wp-block-html" data-origin="gutenberg">' . $script . '</p>';
		}

		$result = wp_de_rtc_parse_post_content_sync_meta( $combined );

		$this->assertWPError( $result );
		$this->assertSame( 'de_rtc_malformed_sync_payload', $result->get_error_code() );
		$this->assertSame( 'sync_meta_not_at_content_edge', $result->get_error_data()['detail'] );
		$this->assertSame( 1, $result->get_error_data()['sync_meta_script_count'] );
	}

	public function data_sync_meta_edge_positions() {
		return array(
			'prefix'  => array( 'prefix' ),
			'trailer' => array( 'trailer' ),
		);
	}

	/**
	 * @covers ::wp_de_rtc_parse_post_content_sync_meta
	 * @covers ::wp_de_rtc_count_post_content_sync_meta_scripts
	 */
	public function test_duplicate_sync_meta_scripts_return_reason_error_data() {
		$content = wp_de_rtc_format_sync_meta(
			'diff-match-patch',
			array( 'version' => '17' )
		)
			. '<!-- wp:paragraph --><p>Hello.</p><!-- /wp:paragraph -->'
			. wp_de_rtc_format_sync_meta(
				'diff-match-patch',
				array( 'version' => '18' )
			);
		$result  = wp_de_rtc_parse_post_content_sync_meta( $content );

		$this->assertWPError( $result );
		$this->assertSame( 'de_rtc_malformed_sync_payload', $result->get_error_code() );
		$this->assertSame( 'duplicate_sync_meta', $result->get_error_data()['detail'] );
		$this->assertSame( 2, $result->get_error_data()['sync_meta_script_count'] );
	}

	/**
	 * @covers ::wp_de_rtc_parse_post_content_sync_meta
	 * @covers ::wp_de_rtc_count_post_content_sync_meta_scripts
	 */
	public function test_non_edge_sync_meta_script_returns_reason_error_data() {
		$script = wp_de_rtc_format_sync_meta(
			'diff-match-patch',
			array( 'version' => '19' )
		);
		$result = wp_de_rtc_parse_post_content_sync_meta(
			'<!-- wp:paragraph --><p>Hello.</p><!-- /wp:paragraph -->'
			. '<!-- wp:paragraph --><p>' . $script . '</p><!-- /wp:paragraph -->'
		);

		$this->assertWPError( $result );
		$this->assertSame( 'de_rtc_malformed_sync_payload', $result->get_error_code() );
		$this->assertSame( 'sync_meta_not_at_content_edge', $result->get_error_data()['detail'] );
		$this->assertSame( 1, $result->get_error_data()['sync_meta_script_count'] );
	}

	/**
	 * @covers ::wp_de_rtc_parse_post_content_sync_meta
	 * @covers ::wp_de_rtc_count_post_content_sync_meta_scripts
	 */
	public function test_parses_html_wrapped_sync_meta_script_at_prefix() {
		$script = wp_de_rtc_format_sync_meta(
			'diff-match-patch',
			array( 'version' => '20' )
		);
		$content = '<!-- wp:paragraph --><p>Hello.</p><!-- /wp:paragraph -->';
		$result = wp_de_rtc_parse_post_content_sync_meta(
			'<!-- wp:html -->' . $script . '<!-- /wp:html -->'
			. $content
		);

		$this->assertIsArray( $result );
		$this->assertSame( $content, $result['content'] );
		$this->assertSame( 'diff-match-patch', $result['sync_meta_format'] );
		$this->assertSame( 'prefix-block', $result['sync_meta_position'] );
	}

	/**
	 * @covers ::wp_de_rtc_add_sync_meta_to_post_content
	 * @covers ::wp_de_rtc_parse_post_content_sync_meta
	 */
	public function test_parses_prefix_sync_meta_and_returns_content_without_meta() {
		$content   = '<!-- wp:paragraph --><p>Hello.</p><!-- /wp:paragraph -->';
		$metadata  = array(
			'baseVersion' => 8,
			'clientId'    => 'editor-a',
		);
		$combined  = wp_de_rtc_add_sync_meta_to_post_content( $content, 'yjs', $metadata, 'prefix' );
		$extracted = wp_de_rtc_parse_post_content_sync_meta( $combined );

		$this->assertIsArray( $extracted );
		$this->assertSame( $content, $extracted['content'] );
		$this->assertSame( $metadata, $extracted['sync_meta'] );
		$this->assertSame( 'yjs', $extracted['sync_meta_format'] );
		$this->assertSame( 'prefix', $extracted['sync_meta_position'] );
	}

	/**
	 * @covers ::wp_de_rtc_parse_post_content_sync_meta
	 */
	public function test_content_without_sync_meta_is_returned_unchanged() {
		$content   = '<!-- wp:paragraph --><p>No metadata.</p><!-- /wp:paragraph -->';
		$extracted = wp_de_rtc_parse_post_content_sync_meta( $content );

		$this->assertSame( $content, $extracted['content'] );
		$this->assertNull( $extracted['sync_meta'] );
		$this->assertNull( $extracted['sync_meta_format'] );
		$this->assertNull( $extracted['sync_meta_position'] );
		$this->assertNull( $extracted['raw_sync_meta'] );
	}

	/**
	 * @covers ::wp_de_rtc_parse_post_content_sync_meta
	 */
	public function test_malformed_json_returns_reason_error_data() {
		$content = '<script type="wp/post-sync-meta" data-sync-meta-format="diff-match-patch">{bad json</script><p>Hello.</p>';
		$result  = wp_de_rtc_parse_post_content_sync_meta( $content );

		$this->assertWPError( $result );
		$this->assertSame( 'de_rtc_malformed_sync_payload', $result->get_error_code() );
		$this->assertSame(
			array(
				'status'          => 400,
				'reason_code'     => 'de_rtc_malformed_sync_payload',
				'detail'          => 'malformed_json',
				'json_error_code' => JSON_ERROR_SYNTAX,
			),
			$result->get_error_data()
		);
	}

	/**
	 * @covers ::wp_de_rtc_format_sync_meta
	 * @covers ::wp_de_rtc_parse_post_content_sync_meta
	 */
	public function test_unknown_format_returns_reason_error_data() {
		$result = wp_de_rtc_format_sync_meta(
			'unknown-format',
			array(
				'version' => 1,
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'de_rtc_unknown_sync_meta_format', $result->get_error_code() );
		$this->assertSame(
			array(
				'status'      => 400,
				'reason_code' => 'de_rtc_unknown_sync_meta_format',
				'format'      => 'unknown-format',
			),
			$result->get_error_data()
		);

		$content = '<script type="wp/post-sync-meta" data-sync-meta-format="unknown-format">{"version":1}</script><p>Hello.</p>';
		$result  = wp_de_rtc_parse_post_content_sync_meta( $content );

		$this->assertWPError( $result );
		$this->assertSame( 'de_rtc_unknown_sync_meta_format', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}
}
