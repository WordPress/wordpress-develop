<?php

/**
 * Tests for the `wp_strict_cross_origin_referrer()` function.
 *
 * @group general
 * @group template
 *
 * @covers ::wp_strict_cross_origin_referrer
 */
class Tests_General_WpStrictCrossOriginReferrer extends WP_UnitTestCase {

	/**
	 * Tests that wp_strict_cross_origin_referrer() outputs the expected meta tag.
	 *
	 * @ticket 65819
	 */
	public function test_wp_strict_cross_origin_referrer_output() {
		$expected = "\t<meta name='referrer' content='strict-origin-when-cross-origin' />\n\t";

		$this->expectOutputString( $expected );
		wp_strict_cross_origin_referrer();
	}
}
