<?php

/**
 * Tests get_status_header_desc function
 *
 * @since 5.9.0
 *
 * @group functions.php
 */
class Tests_Functions_nonce extends WP_UnitTestCase {

	/**
	 * @covers ::wp_nonce_url
	 */
	public function test_wp_nonce_url_defaults() {
		$this->assertSame('https://url.com?_wpnonce=' . wp_create_nonce( -1 ),  wp_nonce_url('https://url.com' ) );
	}

	/**
	 * @covers ::wp_nonce_url
	 */
	public function test_wp_nonce_url_defaults_with_amp() {

		$this->assertSame('https://url.com?ddd=dd&amp;fff=ggg&amp;_wpnonce='  . wp_create_nonce( -1 ),  wp_nonce_url('https://url.com?ddd=dd&fff=ggg' ) );
		$this->assertSame('https://url.com?ddd=dd&amp;fff=ggg&amp;_wpnonce='  . wp_create_nonce( -1 ),  wp_nonce_url('https://url.com?ddd=dd&amp;fff=ggg' ) );
	}


	/**
	 * @covers ::wp_nonce_url
	 */
	public function test_wp_nonce_url_defaults_with_parm() {
		$this->assertSame( 'https://url.com?custom_nonce_name='  . wp_create_nonce( 'custom_nonce_action' ), wp_nonce_url('https://url.com', 'custom_nonce_action', 'custom_nonce_name' ) );
	}

	/**
	 * @covers ::wp_nonce_field
	 */
	public function test_wp_nonce_field() {

		//wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true )
		ob_start();
			wp_nonce_field();
		$out = ob_get_clean();
		$this->assertSame( '<input type="hidden" id="_wpnonce" name="_wpnonce" value="' . wp_create_nonce( -1 ) . '" /><input type="hidden" name="_wp_http_referer" value="" />', $out );
	}

	/**
	 * @covers ::wp_nonce_field
	 */
	public function test_wp_nonce_field_custom_input() {

		//wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true )
		ob_start();
		wp_nonce_field('custom_action', 'custom_input' );
		$out = ob_get_clean();
		$this->assertSame( '<input type="hidden" id="custom_input" name="custom_input" value="' . wp_create_nonce( 'custom_action' ) . '" /><input type="hidden" name="_wp_http_referer" value="" />', $out );
	}
	/**
	 * @covers ::wp_nonce_field
	 */
	public function test_wp_nonce_field_custom_input_no_referer() {

		//wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true )
		ob_start();
		wp_nonce_field('custom_action', 'custom_input', false );
		$out = ob_get_clean();
		$this->assertSame( '<input type="hidden" id="custom_input" name="custom_input" value="' . wp_create_nonce( 'custom_action' ) . '" />', $out );
	}

	/**
	 * @covers ::wp_nonce_field
	 */
	public function test_wp_nonce_field_custom_input_no_referer_echo() {

		//wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true )
		$out = wp_nonce_field('custom_action', 'custom_input', false, false );

		$this->assertSame( '<input type="hidden" id="custom_input" name="custom_input" value="' . wp_create_nonce( 'custom_action' ) . '" />', $out );
	}
	/**
	 * @covers ::wp_referer_field
	 */
	public function test_wp_referer_field_echo() {

		ob_start();
		wp_referer_field();
		$out = ob_get_clean();

		$this->assertSame( '<input type="hidden" name="_wp_http_referer" value="" />', $out );
	}

	/**
	 * @covers ::wp_referer_field
	 */
	public function test_wp_referer_field() {

		$this->assertSame( '<input type="hidden" name="_wp_http_referer" value="" />', wp_referer_field( false ) );
	}


	/**
	 * Data provider for test_get_status_header_desc().
	 *
	 * @return array
	 */
	public function _status_strings() {
		return array(
			array( 200, 'OK' ),
			array( 301, 'Moved Permanently' ),
			array( 404, 'Not Found' ),
			array( 500, 'Internal Server Error' ),

			// A string to make sure that the absint() is working.
			array( '200', 'OK' ),

			// Not recognized codes return empty strings.
			array( 9999, '' ),
			array( 'random', '' ),
		);
	}
}
