<?php
/**
 * @ticket 6821
 *
 * @group functions.php
 *
 * @covers ::wp_filesize
 */

class Tests_for_wp_filesize extends WP_UnitTestCase {

	public function test_pre_wp_filesize_filter() {
		$wp_filesize = new MockAction();
		// check that the wp_filesize function shortcut if integer is returned by the pre_wp_filesize filter
		add_filter( 'pre_wp_filesize', array( $this, 'return_99_for_pre_wp_filesize' ) );
		add_filter( 'wp_filesize', array( $wp_filesize, 'filter' ) );

		$this->assertSame( 99, wp_filesize( null ) );

		remove_filter( 'pre_wp_filesize', array( $this, 'return_99_for_pre_wp_filesize' ) );

		$this->assertSame( 0, $wp_filesize->get_call_count() );

		// check that the pre_wp_filesize function doesn't return if not integer
		add_filter( 'pre_wp_filesize', '__return_empty_string' );

		$this->assertSame( 0, wp_filesize( null ) );

		remove_filter( 'wp_filesize', array( $this, '__return_empty_string' ) );

		$this->assertSame( 1, $wp_filesize->get_call_count() );
	}

	// filter to return an int to set the short return value
	public function return_99_for_pre_wp_filesize() {

		return 99;
	}

	public function test_wp_filesize_returns_false_for_bad_path_and_calls_filters() {
		$pre_wp_filesize = new MockAction();
		$wp_filesize     = new MockAction();

		add_filter( 'pre_wp_filesize', array( $pre_wp_filesize, 'filter' ) );
		add_filter( 'pre_wp_filesize', array( $wp_filesize, 'filter' ) );

		$this->assertSame( 0, wp_filesize( null ) );

		remove_filter( 'pre_wp_filesize', array( $pre_wp_filesize, 'filter' ) );
		remove_filter( 'pre_wp_filesize', array( $wp_filesize, 'filter' ) );

		$this->assertSame( 1, $pre_wp_filesize->get_call_count() );
		$this->assertSame( 1, $wp_filesize->get_call_count() );
	}

	public function test_wp_filesize() {
		$path = DIR_TESTDATA . '/images/waffles.jpg';
		$this->assertSame( 68655, wp_filesize( $path ) );
	}
}
