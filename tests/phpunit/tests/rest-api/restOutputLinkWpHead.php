<?php

/**
 * Tests for rest_output_link_wp_head().
 *
 * @group restapi
 *
 * @covers ::rest_output_link_wp_head
 */
class Tests_REST_API_RestOutputLinkWpHead extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_outputs_rest_api_link_tag() {
		$api_root = get_rest_url();
		$expected = sprintf( '<link rel="https://api.w.org/" href="%s" />', esc_url( $api_root ) );

		$this->expectOutputString( $expected );
		rest_output_link_wp_head();
	}

	/**
	 * @ticket 65819
	 */
	public function test_outputs_nothing_when_rest_url_is_empty() {
		add_filter( 'rest_url', '__return_empty_string' );

		$this->expectOutputString( '' );
		rest_output_link_wp_head();
	}
}
