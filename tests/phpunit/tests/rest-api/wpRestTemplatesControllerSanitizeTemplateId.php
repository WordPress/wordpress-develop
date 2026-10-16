<?php
/**
 * Unit tests covering template ID sanitization.
 *
 * @package WordPress
 * @subpackage REST API
 *
 * @covers WP_REST_Templates_Controller::_sanitize_template_id
 *
 * @group restapi
 */
class Tests_REST_WpRestTemplatesControllerSanitizeTemplateId extends WP_UnitTestCase {
	/**
	 * @ticket 54507
	 * @dataProvider data_sanitize_template_id
	 */
	public function test_sanitize_template_id( $input_id, $sanitized_id ) {
		$endpoint = new WP_REST_Templates_Controller( 'wp_template' );
		$this->assertSame(
			$sanitized_id,
			$endpoint->_sanitize_template_id( $input_id )
		);
	}

	public function data_sanitize_template_id() {
		return array(
			array( 'tt1-blocks/index', 'tt1-blocks//index' ),
			array( 'tt1-blocks//index', 'tt1-blocks//index' ),

			array( 'theme-experiments/tt1-blocks/index', 'theme-experiments/tt1-blocks//index' ),
			array( 'theme-experiments/tt1-blocks//index', 'theme-experiments/tt1-blocks//index' ),
		);
	}
}
