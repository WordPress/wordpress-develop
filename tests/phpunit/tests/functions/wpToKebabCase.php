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

	public function test_wp_to_kebab_case() {
		$this->assertEquals( 'white', _wp_to_kebab_case( 'white' ) );
		$this->assertEquals( 'white-black', _wp_to_kebab_case( 'white+black' ) );
		$this->assertEquals( 'white-black', _wp_to_kebab_case( 'white:black' ) );
		$this->assertEquals( 'white-black', _wp_to_kebab_case( 'white*black' ) );
		$this->assertEquals( 'white-black', _wp_to_kebab_case( 'white.black' ) );
		$this->assertEquals( 'white-black', _wp_to_kebab_case( 'white black' ) );
		$this->assertEquals( 'white-black', _wp_to_kebab_case( 'white	black' ) );
		$this->assertEquals( 'white-to-black', _wp_to_kebab_case( 'white-to-black' ) );
		$this->assertEquals( 'white-2-white', _wp_to_kebab_case( 'white2white' ) );
		$this->assertEquals( 'white-2nd', _wp_to_kebab_case( 'white2nd' ) );
		$this->assertEquals( 'white-2-ndcolor', _wp_to_kebab_case( 'white2ndcolor' ) );
		$this->assertEquals( 'white-2nd-color', _wp_to_kebab_case( 'white2ndColor' ) );
		$this->assertEquals( 'white-2nd-color', _wp_to_kebab_case( 'white2nd_color' ) );
		$this->assertEquals( 'white-23-color', _wp_to_kebab_case( 'white23color' ) );
		$this->assertEquals( 'white-23', _wp_to_kebab_case( 'white23' ) );
		$this->assertEquals( '23-color', _wp_to_kebab_case( '23color' ) );
		$this->assertEquals( 'white-4th', _wp_to_kebab_case( 'white4th' ) );
		$this->assertEquals( 'font-2-xl', _wp_to_kebab_case( 'font2xl' ) );
		$this->assertEquals( 'white-to-white', _wp_to_kebab_case( 'whiteToWhite' ) );
		$this->assertEquals( 'white-t-owhite', _wp_to_kebab_case( 'whiteTOwhite' ) );
		$this->assertEquals( 'whit-eto-white', _wp_to_kebab_case( 'WHITEtoWHITE' ) );
		$this->assertEquals( '42', _wp_to_kebab_case( 42 ) );
		$this->assertEquals( 'ive-done', _wp_to_kebab_case( "i've done" ) );
		$this->assertEquals( 'ffffff', _wp_to_kebab_case( '#ffffff' ) );
		$this->assertEquals( 'ffffff', _wp_to_kebab_case( '$ffffff' ) );
	}
}
