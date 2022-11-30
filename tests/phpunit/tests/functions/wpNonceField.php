<?php

/**
 * Tests for the wp_nonce_field() function.
 *
 * @since 6.1.0
 *
 * @group functions.php
 * @covers ::wp_nonce_field
 */
class Tests_Functions_wpNonceField extends WP_UnitTestCase {

	/**
	 * @ticket 55578
	 */
	public function test_wp_nonce_field() {
		wp_nonce_field();
		$this->expectOutputRegex( '#^<input type="hidden" id="_wpnonce" name="_wpnonce" value=".{10}" /><input type="hidden" name="_wp_http_referer" value="" />$#' );
	}

	/**
	 * @ticket 55578
	 *
	 * @dataProvider data_wp_nonce_field
	 *
	 * @param int|string $action          Action name.
	 * @param string     $name            Nonce name.
	 * @param bool       $referer         Whether to set the referer field for validation.
	 * @param string     $expected_regexp The expected regular expression.
	 */
	public function test_wp_nonce_field_return( $action, $name, $referer, $expected_regexp ) {
		$this->assertMatchesRegularExpression( $expected_regexp, wp_nonce_field( $action, $name, $referer, false ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_wp_nonce_field() {
		return array(
			'default'     => array(
				'action'          => -1,
				'name'            => '_wpnonce',
				'referer'         => true,
				'expected_regexp' => '#^<input type="hidden" id="_wpnonce" name="_wpnonce" value=".{10}" /><input type="hidden" name="_wp_http_referer" value="" />$#',
			),
			'nonce_name'  => array(
				'action'          => -1,
				'name'            => 'nonce_name',
				'referer'         => true,
				'expected_regexp' => '#^<input type="hidden" id="nonce_name" name="nonce_name" value=".{10}" /><input type="hidden" name="_wp_http_referer" value="" />$#',
			),
			'action_name' => array(
				'action'          => 'action_name',
				'name'            => '_wpnonce',
				'referer'         => true,
				'expected_regexp' => '#^<input type="hidden" id="_wpnonce" name="_wpnonce" value="' . wp_create_nonce( 'action_name' ) . '" /><input type="hidden" name="_wp_http_referer" value="" />$#',
			),
			'no_referer'  => array(
				'action'          => -1,
				'name'            => '_wpnonce',
				'referer'         => false,
				'expected_regexp' => '#^<input type="hidden" id="_wpnonce" name="_wpnonce" value=".{10}" />$#',
			),
			'& in name'   => array(
				'action'          => -1,
				'name'            => 'a&b',
				'referer'         => false,
				'expected_regexp' => '#^<input type="hidden" id="a\&amp;b" name="a\&amp;b" value=".{10}" />$#',
			),
		);
	}
}
