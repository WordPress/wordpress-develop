<?php

/**
 * Test the feed.
 *
 * @group feed
 */
class Tests_Feed extends WP_UnitTestCase {
	protected static $enclosure_data = array(
		'url'    => 'http://example.com/sound2.mp3',
		'length' => 12345,
		'type'   => 'audio/mpeg',
	);

	/**
	 * Get the rss_enclosure() output.
	 *
	 * @return string
	 */
	protected function get_rss_enclosure() {
		ob_start();
		rss_enclosure();
		return ob_get_clean();
	}

	/**
	 * Get a multiline enclosure string.
	 *
	 * This function generates a multiline string like
	 * wp_xmlrpc_server::add_enclosure_if_new function.
	 *
	 * @return string
	 */
	protected function get_multiline_enclosure_string() {
		return self::$enclosure_data['url'] . "\n" . self::$enclosure_data['length'] . "\n" . self::$enclosure_data['type'] . "\n";
	}

	/**
	 * @ticket 58798
	 *
	 * @covers rss_enclosure
	 */
	public function test_rss_enclosure_filter() {
		$post_id         = self::factory()->post->create();
		$GLOBALS['post'] = $post_id;

		update_post_meta( $post_id, 'enclosure', $this->get_multiline_enclosure_string() );

		add_filter(
			'rss_enclosure',
			function () {
				return 'filtered_html_link_tag';
			}
		);

		$this->assertSame( 'filtered_html_link_tag', $this->get_rss_enclosure(), 'The `rss_enclosure` filter could not be applied.' );
	}

	/**
	 * @ticket 58798
	 *
	 * @covers ::rss_enclosure
	 */
	public function test_rss_enclosure() {
		$this->assertEmpty( $this->get_rss_enclosure(), 'It should return empty when the global post is not set.' );

		$post_id         = self::factory()->post->create();
		$GLOBALS['post'] = $post_id;

		$this->assertEmpty( $this->get_rss_enclosure(), 'The global post does not have the `enclosure` meta field and should return empty. ' );

		update_post_meta( $post_id, 'enclosure', $this->get_multiline_enclosure_string() );

		$expected = '<enclosure url="' . self::$enclosure_data['url'] . '" length="' . self::$enclosure_data['length'] . '" type="' . self::$enclosure_data['type'] . '" />' . "\n";

		$this->assertSame( $expected, $this->get_rss_enclosure(), 'It should return a valid enclosure tag. ' );

		update_post_meta( $post_id, 'enclosure', self::$enclosure_data['url'] );

		$this->assertEmpty( $this->get_rss_enclosure(), 'It should return empty when the `enclosure` meta field is not saved in a multiline string.' );
	}
}
