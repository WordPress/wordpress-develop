<?php

/**
 * @group formatting * @covers ::wp_basename
 */
class Tests_Formatting_WP_Basename extends WP_UnitTestCase {

	/**
	 * @covers ::wp_basename
	 */
	function test_wp_basename_unix() {
		$this->assertSame(
			'file',
			wp_basename( '/home/test/file' )
		);
	}

	/**
	 * @covers ::wp_basename
	 */
	function test_wp_basename_unix_utf8_support() {
		$this->assertSame(
			'žluťoučký kůň.txt',
			wp_basename( '/test/žluťoučký kůň.txt' )
		);
	}

	/**
	 * @ticket 22138
	 *
	 * @covers ::wp_basename
	 */
	function test_wp_basename_windows() {
		$this->assertSame(
			'file.txt',
			wp_basename( 'C:\Documents and Settings\User\file.txt' )
		);
	}

	/**
	 * @ticket 22138
	 *
	 * @covers ::wp_basename
	 */
	function test_wp_basename_windows_utf8_support() {
		$this->assertSame(
			'щипцы.txt',
			wp_basename( 'C:\test\щипцы.txt' )
		);
	}

}
