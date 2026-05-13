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
		$this->assertStringContainsString( 'type="wp/post-sync-meta"', $script );
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
		$this->assertStringContainsString( 'type="wp/post-sync-meta"', $extracted['raw_sync_meta'] );
		$this->assertStringNotContainsString( 'alert("existing")', $extracted['raw_sync_meta'] );
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
