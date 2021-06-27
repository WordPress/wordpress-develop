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
		$this->assertSame( 'white', _wp_to_kebab_case( 'white' ) );
		$this->assertSame( 'white-black', _wp_to_kebab_case( 'white+black' ) );
		$this->assertSame( 'white-black', _wp_to_kebab_case( 'white:black' ) );
		$this->assertSame( 'white-black', _wp_to_kebab_case( 'white*black' ) );
		$this->assertSame( 'white-black', _wp_to_kebab_case( 'white.black' ) );
		$this->assertSame( 'white-black', _wp_to_kebab_case( 'white black' ) );
		$this->assertSame( 'white-black', _wp_to_kebab_case( 'white	black' ) );
		$this->assertSame( 'white-to-black', _wp_to_kebab_case( 'white-to-black' ) );
		$this->assertSame( 'white-2-white', _wp_to_kebab_case( 'white2white' ) );
		$this->assertSame( 'white-2nd', _wp_to_kebab_case( 'white2nd' ) );
		$this->assertSame( 'white-2-ndcolor', _wp_to_kebab_case( 'white2ndcolor' ) );
		$this->assertSame( 'white-2nd-color', _wp_to_kebab_case( 'white2ndColor' ) );
		$this->assertSame( 'white-2nd-color', _wp_to_kebab_case( 'white2nd_color' ) );
		$this->assertSame( 'white-23-color', _wp_to_kebab_case( 'white23color' ) );
		$this->assertSame( 'white-23', _wp_to_kebab_case( 'white23' ) );
		$this->assertSame( '23-color', _wp_to_kebab_case( '23color' ) );
		$this->assertSame( 'white-4th', _wp_to_kebab_case( 'white4th' ) );
		$this->assertSame( 'font-2-xl', _wp_to_kebab_case( 'font2xl' ) );
		$this->assertSame( 'white-to-white', _wp_to_kebab_case( 'whiteToWhite' ) );
		$this->assertSame( 'white-t-owhite', _wp_to_kebab_case( 'whiteTOwhite' ) );
		$this->assertSame( 'whit-eto-white', _wp_to_kebab_case( 'WHITEtoWHITE' ) );
		$this->assertSame( '42', _wp_to_kebab_case( 42 ) );
		$this->assertSame( 'ive-done', _wp_to_kebab_case( "i've done" ) );
		$this->assertSame( 'ffffff', _wp_to_kebab_case( '#ffffff' ) );
		$this->assertSame( 'ffffff', _wp_to_kebab_case( '$ffffff' ) );
	}
}
