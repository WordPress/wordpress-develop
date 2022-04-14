<?php

/**
 * Tests for the wp_nonce_field() function.
 *
 * @since 6.0.0
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
	 */
	public function test_wp_nonce_field_return( $action, $name, $referer, $expected_reg_exp ) {

		$this->assertMatchesRegularExpression( $expected_reg_exp, wp_nonce_field( $action, $name, $referer, false ) );
	}

	/**
	 * @ticket 55578
	 */
	public function data_wp_nonce_field() {

		return array(
			'default'     => array(
				- 1,
				'_wpnonce',
				true,
				'#^<input type="hidden" id="_wpnonce" name="_wpnonce" value=".{10}" /><input type="hidden" name="_wp_http_referer" value="" />$#',
			),
			'nonce_name'  => array(
				- 1,
				'nonce_name',
				true,
				'#^<input type="hidden" id="nonce_name" name="nonce_name" value=".{10}" /><input type="hidden" name="_wp_http_referer" value="" />$#',
			),
			'action_name' => array(
				'action_name',
				'_wpnonce',
				true,
				'#^<input type="hidden" id="_wpnonce" name="_wpnonce" value="' . wp_create_nonce( 'action_name' ) . '" /><input type="hidden" name="_wp_http_referer" value="" />$#',
			),
			'no_referer'  => array(
				- 1,
				'_wpnonce',
				false,
				'#^<input type="hidden" id="_wpnonce" name="_wpnonce" value=".{10}" />$#',
			),
		);
	}
}
