<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing Ajax save draft functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 6.3.2
 *
 * @group ajax
 *
 * @covers ::wp_ajax_parse-media-shortcode
 */
class Tests_Ajax_wpAjaxParseMediaShortcode extends WP_Ajax_UnitTestCase {
	const SHORTCODE_RETURN_VALUE = 'TEST';
	private static $media_id;
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$media_id = self::factory()->attachment->create_object(
			get_temp_dir() . 'canola.jpg',
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
				'post_name'      => 'restapi-client-fixture-attachment',
				'post_title'     => 'REST API Client Fixture: Attachment',
				'post_date'      => '2017-02-14 00:00:00',
				'post_date_gmt'  => '2017-02-14 00:00:00',
				'post_author'    => 0,
			)
		);
	}
	/**
	 * @dataProvider shortcode_provider
	 */
	public function test_parse_shortcode( array $payload, $expected ) {
		add_shortcode( 'test', array( $this, 'shortcode_test' ) );

		$_POST = array_merge(
			array(
				'action' => 'parse-media-shortcode',
				'type'   => '',
			),
			$payload
		);
		// Make the request.
		try {
			$this->_handleAjax( 'parse-media-shortcode' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}
		// Get the response, it is in heartbeat's response.
		$response = json_decode( $this->_last_response, true );
		$body     = $response['data']['body'] ?? '';
		if ( $body ) {
			$this->assertStringNotContainsString( self::SHORTCODE_RETURN_VALUE, $body );
		}
		$this->assertSame( $expected['success'], $response['success'] );
	}

	public function shortcode_test() {
		return self::SHORTCODE_RETURN_VALUE;
	}

	public function shortcode_provider() {
		return array(
			'gallery_shortcode_is_allowed'         => array(
				'payload'  => array( 'shortcode' => '[gallery ids=" ' . self::$media_id . '"]' ),
				'expected' => array( 'success' => true ),
			),
			'gallery_and_custom_test_shortcode_is_not_allowed' => array(
				'payload'  => array( 'shortcode' => '[gallery ids=" ' . self::$media_id . '"] [test]' ),
				'expected' => array( 'success' => false ),
			),
			'custom_test_shortcode_is_not_allowed' => array(
				'payload'  => array( 'shortcode' => '[test]' ),
				'expected' => array( 'success' => false ),
			),
		);
	}
}
