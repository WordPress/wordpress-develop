<?php

/**
 * Tests for the rss_enclosure() function.
 *
 * @group feed
 *
 * @covers ::rss_enclosure
 */
class Tests_Feed_RssEnclosure extends WP_UnitTestCase {

	/**
	 * @ticket 58798
	 */
	public function test_rss_enclosure_filter() {
		$post_id         = self::factory()->post->create();
		$GLOBALS['post'] = $post_id;

		$valid_enclosure_string = "http://example.com/sound2.mp3\n12345\naudio/mpeg\n";

		update_post_meta( $post_id, 'enclosure', $valid_enclosure_string );

		add_filter(
			'rss_enclosure',
			static function () {
				return 'filtered_html_link_tag';
			}
		);

		$this->assertSame( 'filtered_html_link_tag', get_echo( 'rss_enclosure' ), 'The `rss_enclosure` filter could not be applied.' );
	}

	/**
	 * @ticket 58798
	 */
	public function test_rss_enclosure_when_global_post_is_empty() {
		$this->assertEmpty( get_echo( 'rss_enclosure' ), 'The output should be empty when the global post is not set.' );
	}

	/**
	 * @ticket 58798
	 */
	public function test_rss_enclosure_when_enclosure_meta_field_is_empty() {
		$post_id         = self::factory()->post->create();
		$GLOBALS['post'] = $post_id;

		$this->assertEmpty( get_echo( 'rss_enclosure' ), 'The output should be empty when the global post does not have the `enclosure` meta field.' );
	}

	/**
	 * @ticket 58798
	 *
	 * @dataProvider data_rss_enclosure_with_multiline_enclosure_string
	 */
	public function test_rss_enclosure_with_multiline_enclosure_string( $enclosure_data, $enclosure_string ) {
		$post_id         = self::factory()->post->create();
		$GLOBALS['post'] = $post_id;

		update_post_meta( $post_id, 'enclosure', $enclosure_string );

		$expected = '<enclosure url="' . $enclosure_data['url'] . '" length="' . $enclosure_data['length'] . '" type="' . $enclosure_data['type'] . '" />' . "\n";

		$this->assertSame( $expected, get_echo( 'rss_enclosure' ), 'The output should be a valid enclosure tag.' );
	}

	/**
	 * Data provider for valid enclosure string.
	 *
	 * @return array[]
	 */
	public function data_rss_enclosure_with_multiline_enclosure_string() {
		return array(
			'two-break-lines'         => array(
				array(
					'url'    => 'http://example.com/sound2.mp3',
					'length' => 12345,
					'type'   => 'audio/mpeg',
				),
				"http://example.com/sound2.mp3\n12345\naudio/mpeg",
			),
			'three-break-lines'       => array(
				array(
					'url'    => 'http://example.com/sound2.mp3',
					'length' => 12345,
					'type'   => 'audio/mpeg',
				),
				"http://example.com/sound2.mp3\n12345\naudio/mpeg\n",
			),
			'extra-break-line-at-end' => array(
				array(
					'url'    => 'http://example.com/sound2.mp3',
					'length' => 12345,
					'type'   => 'audio/mpeg',
				),
				"http://example.com/sound2.mp3\n12345\naudio/mpeg\n\n",
			),
			'extra-type-elements'     => array(
				array(
					'url'    => 'http://example.com/sound2.mp3',
					'length' => 12345,
					'type'   => 'audio/mpeg',
				),
				"http://example.com/sound2.mp3\n12345\naudio/mpeg mpga mp2 mp3\n",
			),
		);
	}

	/**
	 * @ticket 58798
	 *
	 * @dataProvider data_rss_enclosure_with_non_valid_enclosure_string
	 */
	public function test_rss_enclosure_with_non_valid_enclosure_string( $enclosure_string ) {
		$post_id         = self::factory()->post->create();
		$GLOBALS['post'] = $post_id;

		update_post_meta( $post_id, 'enclosure', $enclosure_string );

		$this->assertEmpty( get_echo( 'rss_enclosure' ), 'The output should be empty when the `enclosure` meta field is not saved in a multiline string.' );
	}

	/**
	 * Data provider for non-valid enclosure string.
	 *
	 * @return array[]
	 */
	public function data_rss_enclosure_with_non_valid_enclosure_string() {
		return array(
			'empty'          => array( '' ),
			'no-break-lines' => array( 'http://example.com/sound2.mp3 12345 audio/mpeg' ),
			'one-break-line' => array( "http://example.com/sound2.mp3\n12345 audio/mpeg" ),
		);
	}
}
