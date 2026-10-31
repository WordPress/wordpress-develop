<?php

/**
 * Tests for rest_output_rsd().
 *
 * @group restapi
 *
 * @covers ::rest_output_rsd
 */
class Tests_REST_API_RestOutputRsd extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_outputs_wp_api_rsd_element() {
		$api_root = esc_url( get_rest_url() );

		$this->expectOutputRegex(
			'#<api name="WP-API" blogID="1" preferred="false" apiLink="' . preg_quote( $api_root, '#' ) . '" />#'
		);
		rest_output_rsd();
	}

	/**
	 * @ticket 65819
	 */
	public function test_outputs_nothing_when_rest_url_is_empty() {
		add_filter( 'rest_url', '__return_empty_string' );

		$this->expectOutputString( '' );
		rest_output_rsd();
	}
}
