<?php

/**
 * Tests for wp_ext2type().
 *
 * @group functions
 *
 * @covers ::wp_ext2type
 */
class Tests_Functions_WpExt2type extends WP_UnitTestCase {

	/**
	 * Tests that known extensions map to the expected file type.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_known_extensions
	 *
	 * @param string $ext      File extension.
	 * @param string $expected File type.
	 */
	public function test_returns_type_for_known_extension( $ext, $expected ) {
		$this->assertSame( $expected, wp_ext2type( $ext ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_known_extensions() {
		return array(
			'image lowercase' => array( 'jpg', 'image' ),
			'image uppercase' => array( 'PNG', 'image' ),
			'audio'           => array( 'mp3', 'audio' ),
			'video'           => array( 'mp4', 'video' ),
			'document'        => array( 'pdf', 'document' ),
		);
	}

	/**
	 * Tests that an unknown extension returns null.
	 *
	 * @ticket 65819
	 */
	public function test_returns_null_for_unknown_extension() {
		$this->assertNull( wp_ext2type( 'notarealext' ) );
	}
}
