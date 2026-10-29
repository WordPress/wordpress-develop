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

	/**
	 * Tests that tags from files without ID3, such as FLAC, Ogg and M4A, are kept under ID3's names.
	 *
	 * @ticket 00000
	 *
	 * @dataProvider data_wp_add_id3_tag_data_keeps_tags_without_id3
	 *
	 * @param array $data     Data as getID3 reads it.
	 * @param array $expected Metadata expected to be kept.
	 */
	public function test_wp_add_id3_tag_data_keeps_tags_without_id3( $data, $expected ) {
		$metadata = array();

		wp_add_id3_tag_data( $metadata, $data );

		$this->assertSame( $expected, $metadata );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_wp_add_id3_tag_data_keeps_tags_without_id3() {
		return array(
			'vorbis comments (FLAC, Ogg)' => array(
				'data'     => array(
					'tags' => array(
						'vorbiscomment' => array(
							'title'       => array( 'Aurora Rising' ),
							'artist'      => array( 'The Northern Lights Ensemble' ),
							'album'       => array( 'Horizons and Beyond' ),
							'albumartist' => array( 'Various Artists' ),
							'date'        => array( '2024' ),
							'tracknumber' => array( '3' ),
							'discnumber'  => array( '1/2' ),
							'lyrics'      => array( 'First line' ),
						),
					),
				),
				'expected' => array(
					'title'                => 'Aurora Rising',
					'artist'               => 'The Northern Lights Ensemble',
					'album'                => 'Horizons and Beyond',
					'band'                 => 'Various Artists',
					'year'                 => '2024',
					'track_number'         => '3',
					'part_of_a_set'        => '1/2',
					'unsynchronised_lyric' => 'First line',
				),
			),
			'QuickTime (M4A)'             => array(
				'data'     => array(
					'tags' => array(
						'quicktime' => array(
							'title'         => array( 'Aurora Rising' ),
							'album_artist'  => array( 'Various Artists' ),
							'creation_date' => array( '2024' ),
							'track_number'  => array( '3/9' ),
							'disc_number'   => array( '1/2' ),
						),
					),
				),
				'expected' => array(
					'title'         => 'Aurora Rising',
					'band'          => 'Various Artists',
					'year'          => '2024',
					'track_number'  => '3/9',
					'part_of_a_set' => '1/2',
				),
			),
			'ID3 wins over other tags'    => array(
				'data'     => array(
					'id3v2' => array(
						'comments' => array(
							'title' => array( 'From ID3' ),
						),
					),
					'tags'  => array(
						'ape' => array(
							'title' => array( 'From APE' ),
						),
					),
				),
				'expected' => array(
					'title' => 'From ID3',
				),
			),
			'pictures are not text'       => array(
				'data'     => array(
					'tags' => array(
						'quicktime' => array(
							'title'   => array( 'Aurora Rising' ),
							'picture' => array( array( 'data' => 'binary' ) ),
						),
					),
				),
				'expected' => array(
					'title' => 'Aurora Rising',
				),
			),
		);
	}
}
