<?php
/**
 * Testing wp_ajax_get_community_events() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 4.8.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_get_community_events
 */
class Tests_wp_ajax_get_community_events extends WP_Ajax_UnitTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Setup test fixtures.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Setup before each test method.
	 */
	public function set_up(): void {
		parent::set_up();
		add_action( 'admin_init', 'wp_ajax_get_community_events', 1 );
	}

	/**
	 * Tests get community events via AJAX.
	 *
	 * @ticket 65252
	 */
	public function test_get_community_events_success(): void {
		$this->_setRole( 'administrator' );
		wp_set_current_user( self::$admin_id );

		$_POST['location']    = 'Paris';
		$_POST['_ajax_nonce'] = wp_create_nonce( 'community_events' );

		add_filter( 'pre_http_request', array( $this, 'mock_events_api_success' ), 10, 3 );

		try {
			$this->_handleAjax( 'get_community_events' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		remove_filter( 'pre_http_request', array( $this, 'mock_events_api_success' ) );

		$response = json_decode( $this->_last_response, true );

		$this->assertIsArray( $response, 'AJAX response should be a JSON array' );
		$this->assertTrue( $response['success'], 'AJAX response should be successful' );
		$this->assertSame( 'Paris', $response['data']['location']['description'], 'Location description mismatch' );
		$this->assertGreaterThanOrEqual( 1, count( $response['data']['events'] ), 'Events count mismatch' );
		$this->assertSame( 'WordCamp Paris', $response['data']['events'][0]['title'], 'Event title mismatch' );

		// Verify user meta was updated.
		$saved_location = get_user_meta( self::$admin_id, 'community-events-location', true );
		$this->assertSame( 'Paris', $saved_location['description'], 'User meta location description mismatch' );
	}

	/**
	 * Tests get community events with API error.
	 *
	 * @ticket 65252
	 */
	public function test_get_community_events_api_error(): void {
		$this->_setRole( 'administrator' );
		wp_set_current_user( self::$admin_id );

		$_POST['location']    = 'InvalidCity';
		$_POST['_ajax_nonce'] = wp_create_nonce( 'community_events' );

		add_filter( 'pre_http_request', array( $this, 'mock_events_api_error' ), 10, 3 );

		try {
			$this->_handleAjax( 'get_community_events' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		remove_filter( 'pre_http_request', array( $this, 'mock_events_api_error' ) );

		$response = json_decode( $this->_last_response, true );

		$this->assertIsArray( $response, 'AJAX response should be a JSON array' );
		$this->assertFalse( $response['success'], 'AJAX response should fail on API error' );
		$this->assertSame( 'Invalid API response code (404).', $response['data']['error'], 'Error message mismatch' );
	}

	/**
	 * Tests get community events with invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_get_community_events_invalid_nonce(): void {
		$this->_setRole( 'administrator' );
		wp_set_current_user( self::$admin_id );

		$_POST['location']    = 'Paris';
		$_POST['_ajax_nonce'] = 'invalid-nonce';

		try {
			$this->_handleAjax( 'get_community_events' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->_last_response = (string) $e->getMessage();
		}

		$this->assertSame( '-1', $this->_last_response, 'Should return -1 for invalid nonce' );
	}

	/**
	 * Mock HTTP request for Events API success.
	 */
	public function mock_events_api_success(): array {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => json_encode(
				array(
					'location' => array(
						'description' => 'Paris',
						'latitude'    => 48.8566,
						'longitude'   => 2.3522,
						'ip'          => '127.0.0.1',
					),
					'events'   => array(
						array(
							'title'              => 'WordCamp Paris',
							'url'                => 'https://paris.wordcamp.org/2024/',
							'end_unix_timestamp' => time() + 3600,
							'type'               => 'wordcamp',
						),
						array(
							'title'              => 'WordPress Paris Meetup',
							'url'                => 'https://www.meetup.com/WordPress-Paris/',
							'end_unix_timestamp' => time() + 3600,
							'type'               => 'meetup',
						),
					),
					'ttl'      => 3600,
				)
			),
		);
	}

	/**
	 * Mock HTTP request for Events API error.
	 */
	public function mock_events_api_error(): array {
		return array(
			'response' => array( 'code' => 404 ),
			'body'     => json_encode(
				array(
					'error' => 'Unknown API error.',
				)
			),
		);
	}
}
