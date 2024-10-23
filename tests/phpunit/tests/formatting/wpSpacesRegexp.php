<?php

/**
 * Tests for the wp_spaces_regexp function.
 *
 * @group formatting
 *
 * @covers ::wp_spaces_regexp
 */
class Tests_formatting_wpSpacesRegexp extends WP_UnitTestCase {

	/**
	 * @ticket 60319
	 */
	public function test_wp_spaces_regexp() {
		$filter = new MockAction();
		add_filter( 'wp_spaces_regexp', array( $filter, 'filter' ) );

		$this->assertSame( '[\r\n\t ]|\xC2\xA0|&nbsp;', wp_spaces_regexp() );
		// filter not call as this is set up in the init call for site
		$this->assertSame( 0, $filter->get_call_count(), '1st call' );
	}
}
