<?php

/**
 * Tests for the `rsd_link()` function.
 *
 * @group general
 * @group template
 *
 * @covers ::rsd_link
 */
class Tests_General_RsdLink extends WP_UnitTestCase {

	/**
	 * Tests that rsd_link() outputs the expected EditURI link tag.
	 *
	 * @ticket 65819
	 */
	public function test_rsd_link_output() {
		update_option( 'siteurl', 'http://example.org' );

		$expected = '<link rel="EditURI" type="application/rsd+xml" title="RSD" href="http://example.org/xmlrpc.php?rsd" />';

		ob_start();
		rsd_link();
		$actual = trim( ob_get_clean() );

		$this->assertSame( $expected, $actual );
	}
}
