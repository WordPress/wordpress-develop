<?php

/**
 * Tests for the _wp_to_kebab_case() function
 *
 * @since 5.8.0
 *
 * @group functions.php
 * @covers ::_wp_to_kebab_case
 */
class Tests_Functions_wpToKebabCase extends WP_UnitTestCase {

	/**
	 * Tests _wp_to_kebab_case().
	 *
	 * @dataProvider data_wp_to_kebab_case
	 *
	 * @ticket 53397
	 *
	 * @param string $test_value Test value.
	 * @param string $expected   Expected return value.
	 */
	public function test_wp_to_kebab_case( $test_value, $expected ) {
		$this->assertSame( $expected, _wp_to_kebab_case( $test_value ) );
	}

	/**
	 * Data provider for test_wp_to_kebab_case().
	 *
	 * @return array[] Test parameters {
	 *     @type string $test_value Test value.
	 *     @type string $expected   Expected return value.
	 * }
	 */
	public function data_wp_to_kebab_case() {
		return array(
			array( 'white', 'white' ),
			array( 'white+black', 'white-black' ),
			array( 'white:black', 'white-black' ),
			array( 'white*black', 'white-black' ),
			array( 'white.black', 'white-black' ),
			array( 'white black', 'white-black' ),
			array( 'white	black', 'white-black' ),
			array( 'white-to-black', 'white-to-black' ),
			array( 'white2white', 'white-2-white' ),
			array( 'white2nd', 'white-2nd' ),
			array( 'white2ndcolor', 'white-2-ndcolor' ),
			array( 'white2ndColor', 'white-2nd-color' ),
			array( 'white2nd_color', 'white-2nd-color' ),
			array( 'white23color', 'white-23-color' ),
			array( 'white23', 'white-23' ),
			array( '23color', '23-color' ),
			array( 'white4th', 'white-4th' ),
			array( 'font2xl', 'font-2-xl' ),
			array( 'whiteToWhite', 'white-to-white' ),
			array( 'whiteTOwhite', 'white-t-owhite' ),
			array( 'WHITEtoWHITE', 'whit-eto-white' ),
			array( 42, '42' ),
			array( "i've done", 'ive-done' ),
			array( '#ffffff', 'ffffff' ),
			array( '$ffffff', 'ffffff' ),
		);
	}
}
