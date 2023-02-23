<?php
/**
 * Test wp_strip_all_tags()
 *
 * @group formatting
 *
 * @covers ::wp_strip_all_tags
 */
class Tests_Formatting_wpStripAllTags extends WP_UnitTestCase {

	public function test_wp_strip_all_tags() {

		$text = 'lorem<br />ipsum';
		$this->assertSame( 'loremipsum', wp_strip_all_tags( $text ) );

		$text = "lorem<br />\nipsum";
		$this->assertSame( "lorem\nipsum", wp_strip_all_tags( $text ) );

		// Test removing breaks is working.
		$text = 'lorem<br />ipsum';
		$this->assertSame( 'loremipsum', wp_strip_all_tags( $text, true ) );

		// Test script / style tag's contents is removed.
		$text = 'lorem<script>alert(document.cookie)</script>ipsum';
		$this->assertSame( 'loremipsum', wp_strip_all_tags( $text ) );

		$text = "lorem<style>* { display: 'none' }</style>ipsum";
		$this->assertSame( 'loremipsum', wp_strip_all_tags( $text ) );

		// Test "marlformed" markup of contents.
		$text = "lorem<style>* { display: 'none' }<script>alert( document.cookie )</script></style>ipsum";
		$this->assertSame( 'loremipsum', wp_strip_all_tags( $text ) );
	}

	/**
	 * Tests that `wp_strip_all_tags()` returns an empty string when null is passed.
	 *
	 * @ticket 56434
	 */
	public function test_wp_strip_all_tags_should_return_empty_string_for_a_null_arg() {
		$this->assertSame( '', wp_strip_all_tags( null ) );
	}

	/**
	 * Tests that `wp_strip_all_tags()` triggers a warning and returns
	 * an empty string when passed a non-string argument.
	 *
	 * @ticket 56434
	 *
	 * @dataProvider data_wp_strip_all_tags_should_return_empty_string_and_trigger_an_error_for_non_string_arg
	 *
	 * @param mixed $non_string A non-string value.
	 */
	public function test_wp_strip_all_tags_should_return_empty_string_and_trigger_an_error_for_non_string_arg( $non_string ) {
		$type = gettype( $non_string );
		$this->expectError();
		$this->expectErrorMessage( "Warning: wp_strip_all_tags expects parameter #1 (\$text) to be a string, $type given." );
		$this->assertSame( '', wp_strip_all_tags( $non_string ) );
	}

	/**
	 * Data provider for test_wp_strip_all_tags_should_return_empty_string_and_trigger_an_error_for_non_string_arg().
	 *
	 * @return array[]
	 */
	public function data_wp_strip_all_tags_should_return_empty_string_and_trigger_an_error_for_non_string_arg() {
		return array(
			'an empty array'     => array( 'non_string' => array() ),
			'a non-empty array'  => array( 'non_string' => array( 'a string' ) ),
			'an empty object'    => array( 'non_string' => new stdClass() ),
			'a non-empty object' => array( 'non_string' => (object) array( 'howdy' => 'admin' ) ),
		);
	}

	/**
	 * Tests that `wp_strip_all_tags()` casts scalar values to string.
	 *
	 * @ticket 56434
	 *
	 * @dataProvider data_wp_strip_all_tags_should_cast_scalar_values_to_string
	 *
	 * @param mixed $text A scalar value.
	 */
	public function test_wp_strip_all_tags_should_cast_scalar_values_to_string( $text ) {
		$this->assertSame( (string) $text, wp_strip_all_tags( $text ) );
	}

	/**
	 * Data provider for test_wp_strip_all_tags_should_cast_scalar_values_to_string()/
	 *
	 * @return array[]
	 */
	public function data_wp_strip_all_tags_should_cast_scalar_values_to_string() {
		return array(
			'(int) 0'      => array( 'text' => 0 ),
			'(int) 1'      => array( 'text' => 1 ),
			'(int) -1'     => array( 'text' => -1 ),
			'(float) 0.0'  => array( 'text' => 0.0 ),
			'(float) 1.0'  => array( 'text' => 1.0 ),
			'(float) -1.0' => array( 'text' => -1.0 ),
			'(bool) false' => array( 'text' => false ),
			'(bool) true'  => array( 'text' => true ),
		);
	}
}

