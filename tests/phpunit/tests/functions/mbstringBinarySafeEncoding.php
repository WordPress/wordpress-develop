<?php
/**
 * Tests for the mbstring_binary_safe_encoding function.
 *
 * @group functions.php
 *
 * @covers ::mbstring_binary_safe_encoding
 */#
class Tests_functions_mbstringBinarySafeEncoding extends WP_UnitTestCase {

	/**
	 * Test that the function resets the encoding
	 *
	 * @ticket 59790
	 * @covers ::mbstring_binary_safe_encoding
	 */
	public function test_mbstring_binary_safe_encoding() {
		// Set a different encoding
		mb_internal_encoding( 'UTF-8' );

		// Call the function
		mbstring_binary_safe_encoding( true );

		// Check that the encoding was reset
		$this->assertEquals( 'UTF-8', mb_internal_encoding() );
	}

	/**
	 * Test that the function preserves the encoding
	 *
	 * @ticket 59790
	 * @covers ::mbstring_binary_safe_encoding
	 */
	public function test_mbstring_binary_safe_encoding_false() {
		$encoding = 'UTF-8';
		mb_internal_encoding( $encoding );

		// Call the function
		mbstring_binary_safe_encoding();

		// Check that the encoding was preserved
		$this->assertEquals( $encoding, mb_internal_encoding() );
	}

	/**
	 * Test that the function preserves the encoding
	 *
	 * @ticket 59790
	 * @covers ::reset_mbstring_encoding
	 */
	public function test_reset_mbstring_encoding() {
		// Set a different encoding
		mb_internal_encoding( 'UTF-8' );

		// Call the function
		reset_mbstring_encoding();

		// Check that the encoding was reset
		$this->assertEquals( 'UTF-8', mb_internal_encoding() );
	}
}
