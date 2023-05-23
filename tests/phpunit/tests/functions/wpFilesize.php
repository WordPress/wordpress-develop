<?php

/**
 * Tests for the wp_filesize() function.
 *
 * @group functions.php
 * @covers ::wp_filesize
 */
class Tests_Functions_wpFilesize extends WP_UnitTestCase {

	/**
	 * @ticket 49412
	 */
	public function test_wp_filesize() {
		$file = DIR_TESTDATA . '/images/test-image-upside-down.jpg';

		$this->assertSame( filesize( $file ), wp_filesize( $file ) );
	}

	/**
	 * @ticket 49412
	 */
	public function test_wp_filesize_filters() {
		$file = DIR_TESTDATA . '/images/test-image-upside-down.jpg';

		add_filter(
			'wp_filesize',
			static function() {
				return 999;
			}
		);

		$this->assertSame( 999, wp_filesize( $file ) );

		add_filter(
			'pre_wp_filesize',
			static function() {
				return 111;
			}
		);

		$this->assertSame( 111, wp_filesize( $file ) );
	}

	/**
	 * @ticket 49412
	 */
	public function test_wp_filesize_with_nonexistent_file() {
		$file = 'nonexistent/file.jpg';

		$this->assertSame( 0, wp_filesize( $file ) );
	}
}
