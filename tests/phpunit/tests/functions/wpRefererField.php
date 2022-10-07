<?php

/**
 * Tests for the wp_referer_field() function.
 *
 * @since 6.1.0
 *
 * @group functions.php
 * @covers ::wp_referer_field
 */
class Tests_Functions_wpRefererField extends WP_UnitTestCase {

	/**
	 * @ticket 55578
	 */
	public function test_wp_referer_field() {

		$_SERVER['REQUEST_URI'] = '/test/';
		wp_referer_field();
		$this->expectOutputString( '<input type="hidden" name="_wp_http_referer" value="/test/" />' );
	}

	/**
	 * @ticket 55578
	 */
	public function test_wp_referer_field_return() {

		$_SERVER['REQUEST_URI'] = '/test/';

		$this->assertSame( '<input type="hidden" name="_wp_http_referer" value="/test/" />', wp_referer_field( false ) );
	}
}
