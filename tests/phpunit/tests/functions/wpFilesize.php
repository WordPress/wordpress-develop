<?php

/**
 * Tests for the wp_filesize() function.
 *
 * @group functions
 *
 * @covers ::wp_filesize
 */
class Tests_Functions_wpFilesize extends WP_UnitTestCase {

	const TEST_FILE = DIR_TESTDATA . '/images/test-image-upside-down.jpg';

	/**
	 * @ticket 49412
	 */
	public function test_wp_filesize(): void {
		$this->assertSame( filesize( self::TEST_FILE ), wp_filesize( self::TEST_FILE ) );
	}

	/**
	 * @ticket 49412
	 * @ticket 65670
	 */
	public function test_wp_filesize_filters(): void {
		add_filter( 'wp_filesize', static fn () => 999 );
		$this->assertSame( 999, wp_filesize( self::TEST_FILE ) );

		add_filter( 'wp_filesize', static fn () => '9991', 100 );
		$this->assertSame( 9991, wp_filesize( self::TEST_FILE ) );

		add_filter( 'pre_wp_filesize', static fn () => 111 );
		$this->assertSame( 111, wp_filesize( self::TEST_FILE ) );

		add_filter( 'pre_wp_filesize', static fn () => '2222', 100 );
		$this->assertSame( 2222, wp_filesize( self::TEST_FILE ) );

		add_filter( 'pre_wp_filesize', static fn () => -100, 200 );
		$this->assertSame( 9991, wp_filesize( self::TEST_FILE ) );
	}

	/**
	 * @ticket 49412
	 */
	public function test_wp_filesize_with_nonexistent_file(): void {
		$this->assertSame( 0, wp_filesize( 'nonexistent/file.jpg' ) );
	}

	/**
	 * @ticket 65670
	 */
	public function test_wp_filesize_pre_wp_filesize_filter_null(): void {
		add_filter( 'pre_wp_filesize', '__return_null' );

		$this->assertSame( filesize( self::TEST_FILE ), wp_filesize( self::TEST_FILE ) );
	}

	/**
	 * @ticket 65670
	 *
	 * @dataProvider data_wp_filesize_pre_wp_filesize_filter_negative
	 *
	 * @param float|int|string $value Negative value returned by the filter.
	 */
	public function test_wp_filesize_pre_wp_filesize_filter_negative( $value ): void {
		add_filter( 'pre_wp_filesize', static fn () => $value );

		$this->assertSame( filesize( self::TEST_FILE ), wp_filesize( self::TEST_FILE ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: float|int|string }>
	 */
	public function data_wp_filesize_pre_wp_filesize_filter_negative(): array {
		return array(
			'negative int'            => array( -1 ),
			'negative numeric string' => array( '-1' ),
			'negative float'          => array( -1.5 ),
		);
	}

	/**
	 * @ticket 65670
	 *
	 * @dataProvider data_wp_filesize_filter_invalid_value
	 *
	 * @param mixed $value
	 */
	public function test_wp_filesize_wp_filesize_filter_invalid_value( $value ): void {
		add_filter( 'wp_filesize', static fn () => $value );
		$this->assertSame( 0, wp_filesize( self::TEST_FILE ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: mixed }>
	 */
	public function data_wp_filesize_filter_invalid_value(): array {
		return array(
			'negative' => array( -1 ),
			'null'     => array( null ),
			'array'    => array( array( 'bad', 'array' ) ),
		);
	}
}
