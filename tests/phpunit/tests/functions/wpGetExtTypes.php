<?php

/**
 * Tests for wp_get_ext_types().
 *
 * @group functions
 *
 * @covers ::wp_get_ext_types
 */
class Tests_Functions_WpGetExtTypes extends WP_UnitTestCase {

	/**
	 * Tests that wp_get_ext_types() returns the expected type keys.
	 *
	 * @ticket 65819
	 */
	public function test_returns_default_type_keys() {
		$types = wp_get_ext_types();

		$this->assertSameSets(
			array(
				'image',
				'audio',
				'video',
				'document',
				'spreadsheet',
				'interactive',
				'text',
				'archive',
				'code',
			),
			array_keys( $types )
		);
	}

	/**
	 * Tests representative extensions for a few file types.
	 *
	 * @ticket 65819
	 */
	public function test_includes_representative_extensions() {
		$types = wp_get_ext_types();

		$this->assertContains( 'jpg', $types['image'] );
		$this->assertContains( 'mp3', $types['audio'] );
		$this->assertContains( 'mp4', $types['video'] );
		$this->assertContains( 'pdf', $types['document'] );
		$this->assertContains( 'zip', $types['archive'] );
	}

	/**
	 * Tests that the ext2type filter can modify the map.
	 *
	 * @ticket 65819
	 */
	public function test_filter_can_modify_ext_types() {
		add_filter(
			'ext2type',
			static function ( $types ) {
				$types['custom'] = array( 'xyz' );
				return $types;
			}
		);

		$types = wp_get_ext_types();

		$this->assertArrayHasKey( 'custom', $types );
		$this->assertSame( array( 'xyz' ), $types['custom'] );
	}
}
