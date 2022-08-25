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
	 * Tests that `wp_strip_all_tags()` casts non-string scalar values to string.
	 *
	 * @ticket 56434
	 *
	 * @dataProvider data_non_string_scalar_values
	 *
	 * @param mixed $scalar A non-string scalar value.
	 */
	public function test_wp_strip_all_tags_should_cast_non_string_scalar_values_to_string( $scalar ) {
		$this->assertSame( (string) $scalar, wp_strip_all_tags( $scalar ) );
	}

	/**
	 * Data provider: Provides non-string scalar values.
	 *
	 * @return array
	 */
	public function data_non_string_scalar_values() {
		return array(
			'(int) 0'      => array( 'scalar' => 0 ),
			'(int) 1'      => array( 'scalar' => 1 ),
			'(int) 2'      => array( 'scalar' => 2 ),
			'(int) -1'     => array( 'scalar' => -1 ),
			'(float) 0.0'  => array( 'scalar' => 0.0 ),
			'(float) 1.0'  => array( 'scalar' => 1.0 ),
			'(float) -1.0' => array( 'scalar' => -1.0 ),
			'(bool) false' => array( 'scalar' => false ),
			'(bool) true'  => array( 'scalar' => true ),
		);
	}

	/**
	 * Tests that `wp_strip_all_tags()` returns an empty string for non-scalar values.
	 *
	 * @ticket 56434
	 *
	 * @dataProvider data_non_scalar_values
	 *
	 * @param mixed $non_scalar A non-scalar value.
	 */
	public function test_wp_strip_all_tags_should_return_an_empty_string_for_non_scalar_values( $non_scalar ) {
		$this->assertSame( '', wp_strip_all_tags( $non_scalar ) );
	}

	/**
	 * Data provider: Provides non-scalar values.
	 *
	 * @return array
	 */
	public function data_non_scalar_values() {
		return array(
			'null'               => array( 'non_scalar' => null ),
			'an empty array'     => array( 'non_scalar' => array() ),
			'a non-empty array'  => array( 'non_scalar' => array( 'howdy', 'admin' ) ),
			'an empty object'    => array( 'non_scalar' => new stdClass() ),
			'a non-empty object' => array( 'non_scalar' => (object) array( 'howdy', 'admin' ) ),
		);
	}
}

