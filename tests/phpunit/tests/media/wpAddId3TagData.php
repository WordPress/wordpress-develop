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
	 * Test that wp_add_id3_tag_data handles non-string data without fatal error.
	 *
	 * @ticket 63529
	 */
	public function test_wp_add_id3_tag_data_handles_non_string_data() {
		$metadata = array();

		// Simulate ID3 data with array values that cause wp_kses_post to fail
		$data = array(
			'id3v2' => array(
				'comments' => array(
					'artist'          => 'The Northern Lights Ensemble',
					'album'           => 'Horizons and Beyond',
					'title'           => 'Aurora Rising',
					'involved_people' => array(
						array(
							'role' => 'Mastered by Lead Audio Engineer',
							'name' => 'Emma Clarke',
						),
						array(
							'role' => 'Mixed by Senior Sound Designer',
							'name' => 'Daniel Perez',
						),
					),
				),
			),
		);

		// This should not cause a fatal error when wp_kses_post receives array values
		wp_add_id3_tag_data( $metadata, $data );

		// Verify the function handled the array data gracefully
		$this->assertIsArray( $metadata );
		$this->assertArrayHasKey( 'artist', $metadata );
		$this->assertArrayHasKey( 'album', $metadata );
		$this->assertArrayHasKey( 'title', $metadata );
		$this->assertArrayHasKey( 'involved_people', $metadata );
	}
}
