<?php
/**
 * Tests for the `wp_add_id3_tag_data()` function.
 *
 * @group media
 * @covers ::wp_add_id3_tag_data
 */
class Tests_Media_WpAddId3TagData extends WP_UnitTestCase {

	/**
	 * Performs cleanup after each test.
	 */
	public function tear_down() {
		$this->remove_added_uploads();
		parent::tear_down();
	}

	/**
	 * Test wp_add_id3_tag_data() with ID3v2 comments.
	 */
	public function test_wp_add_id3_tag_data_id3v2_comments() {
		$metadata = array();
		$data     = array(
			'id3v2' => array(
				'comments' => array(
					'title'  => array( 'Test Title' ),
					'artist' => array( 'Test Artist' ),
					'album'  => array( 'Test Album' ),
					'year'   => array( '2024' ),
					'length' => array( '3:45' ), // Should be ignored
				),
			),
		);

		wp_add_id3_tag_data( $metadata, $data );

		$this->assertEquals( 'Test Title', $metadata['title'] );
		$this->assertEquals( 'Test Artist', $metadata['artist'] );
		$this->assertEquals( 'Test Album', $metadata['album'] );
		$this->assertEquals( '2024', $metadata['year'] );
		$this->assertArrayNotHasKey( 'length', $metadata );
	}

	/**
	 * Test wp_add_id3_tag_data() with ID3v1 comments.
	 */
	public function test_wp_add_id3_tag_data_id3v1_comments() {
		$metadata = array();
		$data     = array(
			'id3v1' => array(
				'comments' => array(
					'title'  => array( 'Test Title' ),
					'artist' => array( 'Test Artist' ),
					'album'  => array( 'Test Album' ),
				),
			),
		);

		wp_add_id3_tag_data( $metadata, $data );

		$this->assertEquals( 'Test Title', $metadata['title'] );
		$this->assertEquals( 'Test Artist', $metadata['artist'] );
		$this->assertEquals( 'Test Album', $metadata['album'] );
	}

	/**
	 * Test wp_add_id3_tag_data() with array values.
	 */
	public function test_wp_add_id3_tag_data_array_values() {
		$metadata = array();
		$data     = array(
			'id3v2' => array(
				'comments' => array(
					'title'  => array( 'Part 1', 'Part 2' ),
					'artist' => array( 'Artist 1', 'Artist 2' ),
				),
			),
		);

		wp_add_id3_tag_data( $metadata, $data );

		$this->assertEquals( 'Part 1', $metadata['title'] );
		$this->assertEquals( 'Artist 1', $metadata['artist'] );
	}

	/**
	 * Test wp_add_id3_tag_data() with APIC image data.
	 */
	public function test_wp_add_id3_tag_data_apic_image() {
		$metadata = array();
		$data     = array(
			'id3v2' => array(
				'APIC' => array(
					array(
						'data'         => 'image_data',
						'image_mime'   => 'image/jpeg',
						'image_width'  => 800,
						'image_height' => 600,
					),
				),
			),
		);

		wp_add_id3_tag_data( $metadata, $data );

		$this->assertArrayHasKey( 'image', $metadata );
		$this->assertEquals( 'image_data', $metadata['image']['data'] );
		$this->assertEquals( 'image/jpeg', $metadata['image']['mime'] );
		$this->assertEquals( 800, $metadata['image']['width'] );
		$this->assertEquals( 600, $metadata['image']['height'] );
	}

	/**
	 * Test wp_add_id3_tag_data() with comments picture.
	 */
	public function test_wp_add_id3_tag_data_comments_picture() {
		$metadata = array();
		$data     = array(
			'comments' => array(
				'picture' => array(
					array(
						'data'       => 'image_data',
						'image_mime' => 'image/png',
					),
				),
			),
		);

		wp_add_id3_tag_data( $metadata, $data );

		$this->assertArrayHasKey( 'image', $metadata );
		$this->assertEquals( 'image_data', $metadata['image']['data'] );
		$this->assertEquals( 'image/png', $metadata['image']['mime'] );
	}

	/**
	 * Test wp_add_id3_tag_data() with terms_of_use fix.
	 */
	public function test_wp_add_id3_tag_data_terms_of_use_fix() {
		$metadata = array();
		$data     = array(
			'id3v2' => array(
				'comments' => array(
					'terms_of_use' => array( 'yright notice. All rights reserved.' ),
				),
			),
		);

		wp_add_id3_tag_data( $metadata, $data );

		$this->assertEquals( 'Copyright notice. All rights reserved.', $metadata['terms_of_use'] );
	}

	/**
	 * Test wp_add_id3_tag_data() with HTML sanitization.
	 */
	public function test_wp_add_id3_tag_data_html_sanitization() {
		$metadata = array();
		$data     = array(
			'id3v2' => array(
				'comments' => array(
					'title'  => array( '<script>alert("XSS")</script>Test Title' ),
					'artist' => array( '<a href="javascript:alert(1)">Test Artist</a>' ),
					'lyric'  => array( '<div class="test"><p>Test lyric</p><script>alert("test");</script><span>More text</span></div>' ),
				),
			),
		);

		wp_add_id3_tag_data( $metadata, $data );

		// <script> tags are removed, but their content is not kept; only allowed tags and their content remain.
		$this->assertEquals( 'alert("XSS")Test Title', $metadata['title'] );
		// <a> tags are allowed, but javascript: URLs are stripped from href, so only <a> remains.
		$this->assertEquals( '<a href="alert(1)">Test Artist</a>', $metadata['artist'] );
		$this->assertEquals( '<div class="test"><p>Test lyric</p>alert("test");<span>More text</span></div>', $metadata['lyric'] );
	}

	/**
	 * Test wp_add_id3_tag_data() with empty arrays.
	 */
	public function test_wp_add_id3_tag_data_empty_arrays() {
		$metadata = array();
		$data     = array(
			'id3v2' => array(
				'comments' => array(
					'title'  => array(),
					'artist' => array(),
				),
			),
		);

		wp_add_id3_tag_data( $metadata, $data );

		$this->assertArrayNotHasKey( 'title', $metadata );
		$this->assertArrayNotHasKey( 'artist', $metadata );
	}

	/**
	 * Test wp_add_id3_tag_data() with special characters.
	 */
	public function test_wp_add_id3_tag_data_special_characters() {
		$metadata = array();
		$data     = array(
			'id3v2' => array(
				'comments' => array(
					'title'  => array( 'Special chars: é, ñ, 漢字, 😀' ),
					'artist' => array( 'Artist with & special chars' ),
				),
			),
		);

		wp_add_id3_tag_data( $metadata, $data );

		$this->assertEquals( 'Special chars: é, ñ, 漢字, 😀', $metadata['title'] );
		$this->assertEquals( 'Artist with &amp; special chars', $metadata['artist'] );
	}

	/**
	 * Test wp_add_id3_tag_data() with multiple APIC images.
	 */
	public function test_wp_add_id3_tag_data_multiple_apic() {
		$metadata = array();
		$data     = array(
			'id3v2' => array(
				'APIC' => array(
					array(
						'data'         => 'front_cover_data',
						'image_mime'   => 'image/jpeg',
						'image_width'  => 800,
						'image_height' => 600,
					),
					array(
						'data'         => 'back_cover_data',
						'image_mime'   => 'image/png',
						'image_width'  => 400,
						'image_height' => 300,
					),
				),
			),
		);

		wp_add_id3_tag_data( $metadata, $data );

		$this->assertArrayHasKey( 'image', $metadata );
		$this->assertEquals( 'front_cover_data', $metadata['image']['data'] );
		$this->assertEquals( 'image/jpeg', $metadata['image']['mime'] );
		$this->assertEquals( 800, $metadata['image']['width'] );
		$this->assertEquals( 600, $metadata['image']['height'] );
	}

	/**
	 * Test wp_add_id3_tag_data() with very long values.
	 */
	public function test_wp_add_id3_tag_data_long_values() {
		$metadata = array();
		$long_title = str_repeat( 'a', 1000 );
		$data     = array(
			'id3v2' => array(
				'comments' => array(
					'title'  => array( $long_title ),
					'artist' => array( str_repeat( 'b', 500 ) ),
				),
			),
		);

		wp_add_id3_tag_data( $metadata, $data );

		$this->assertEquals( $long_title, $metadata['title'] );
		$this->assertEquals( str_repeat( 'b', 500 ), $metadata['artist'] );
	}

	/**
	 * Test wp_add_id3_tag_data() with different character encodings.
	 */
	public function test_wp_add_id3_tag_data_different_encodings() {
		$metadata = array();
		$data     = array(
			'id3v2' => array(
				'comments' => array(
					'title'  => array( 'Title with UTF-8: é, ñ, 漢字' ),
					'artist' => array( 'Artist with ISO-8859-1: é, ñ' ),
				),
				'encoding' => 'UTF-8',
			),
			'id3v1' => array(
				'comments' => array(
					'title'  => array( 'Title with ISO-8859-1: é, ñ' ),
				),
				'encoding' => 'ISO-8859-1',
			),
		);

		wp_add_id3_tag_data( $metadata, $data );

		$this->assertEquals( 'Title with UTF-8: é, ñ, 漢字', $metadata['title'] );
		$this->assertEquals( 'Artist with ISO-8859-1: é, ñ', $metadata['artist'] );
	}
}
