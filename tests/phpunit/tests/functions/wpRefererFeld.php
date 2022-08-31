<?php

/**
 * @group functions.php
 *
 * @covers ::wp_referer_field
 */
class Tests_Functions_wpRefererField extends WP_UnitTestCase {

	/**
	 * @ticket 54106
	 */
	public function test_wp_referer_field() {
		$this->assertSame( '<input type="hidden" name="_wp_http_referer" value="" />', get_echo( 'wp_referer_field' ) );
	}

	/**
	 * @ticket 54106
	 */
	public function test_wp_referer_field_no_echo() {
		$actual = wp_referer_field( false );

		$this->assertSame( '<input type="hidden" name="_wp_http_referer" value="" />', $actual );
	}

	/**
	 * @ticket 54106
	 */
	public function test_wp_referer_field_with_referer() {
		$old_request_uri        = $_SERVER['REQUEST_URI'];
		$_SERVER['REQUEST_URI'] = 'edit.php?_wp_http_referer=edit.php';

		$actual = wp_referer_field( false );

		$_SERVER['REQUEST_URI'] = $old_request_uri;

		$this->assertSame( '<input type="hidden" name="_wp_http_referer" value="edit.php" />', $actual );
	}
}

