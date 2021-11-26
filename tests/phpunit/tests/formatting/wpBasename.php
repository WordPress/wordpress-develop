<?php

/**
 * @group formatting

 * @covers ::wp_basename
 */
class Tests_Formatting_wpBasename extends WP_UnitTestCase {

	public function test_wp_basename_unix() {
		$this->assertSame(
			'file',
			wp_basename( '/home/test/file' )
		);
	}

	public function test_wp_basename_unix_utf8_support() {
		$this->assertSame(
			'žluťoučký kůň.txt',
			wp_basename( '/test/žluťoučký kůň.txt' )
		);
	}

	/**
	 * @ticket 22138
	 */
	public function test_wp_basename_windows() {
		$this->assertSame(
			'file.txt',
			wp_basename( 'C:\Documents and Settings\User\file.txt' )
		);
	}

	/**
	 * @ticket 22138
	 */
	public function test_wp_basename_windows_utf8_support() {
		$this->assertSame(
			'щипцы.txt',
			wp_basename( 'C:\test\щипцы.txt' )
		);
	}
}
