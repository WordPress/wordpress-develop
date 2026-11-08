<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_get_community_events() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 *
 * @group ajax
 *
 * @covers ::wp_ajax_get_community_events
 */
class Tests_Admin_Includes_AjaxActions_getCommunityEvents extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Original $_SERVER values.
	 *
	 * @var array
	 */
	private $server_backup;

	/**
	 * Requests made to the Events API.
	 *
	 * @var array[]
	 */
	private $requests = array();

	/**
	 * Mocked Events API response, or a WP_Error.
	 *
	 * @var array|WP_Error
	 */
	private $api_response;

	/**
	 * Setup test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Setup before each test method.
	 */
	public function set_up(): void {
		parent::set_up();

		// The test harness does not register this action.
		add_action( 'wp_ajax_get-community-events', 'wp_ajax_get_community_events', 1 );
		add_filter( 'pre_http_request', array( $this, 'mock_events_api' ), 10, 3 );

		// WP_Community_Events sends the anonymized client IP, so make it predictable.
		$this->server_backup = $_SERVER;
		foreach ( array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED' ) as $header ) {
			unset( $_SERVER[ $header ] );
		}
		$_SERVER['REMOTE_ADDR'] = '203.0.113.5';

		$this->api_response = $this->get_api_response_body();

		wp_set_current_user( self::$admin_id );
	}

	/**
	 * Tear down after each test method.
	 */
	public function tear_down(): void {
		$_SERVER = $this->server_backup;
		parent::tear_down();
	}

	/**
	 * Mocks requests to the Events API.
	 *
	 * @param false|array|WP_Error $response Preemptive response.
	 * @param array                $args     Request arguments.
	 * @param string               $url      Request URL.
	 * @return array|WP_Error Mocked response.
	 */
	public function mock_events_api( $response, $args, $url ) {
		$this->requests[] = array(
			'url'  => $url,
			'args' => $args,
		);

		if ( is_wp_error( $this->api_response ) ) {
			return $this->api_response;
		}

		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( $this->api_response['body'] ),
			'response' => array(
				'code'    => $this->api_response['code'],
				'message' => '',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Returns a successful Events API response with one expired and two upcoming events.
	 *
	 * @return array
	 */
	private function get_api_response_body(): array {
		return array(
			'code' => 200,
			'body' => array(
				'location' => array(
					'description' => 'Paris',
					'latitude'    => '48.857',
					'longitude'   => '2.352',
					'country'     => 'FR',
					'ip'          => '198.51.100.99',
				),
				'events'   => array(
					array(
						'type'               => 'meetup',
						'title'              => 'Past Meetup',
						'end_unix_timestamp' => time() - DAY_IN_SECONDS,
					),
					array(
						'type'               => 'meetup',
						'title'              => 'Paris Meetup',
						'end_unix_timestamp' => time() + DAY_IN_SECONDS,
					),
					array(
						'type'               => 'wordcamp',
						'title'              => 'WordCamp Paris',
						'end_unix_timestamp' => time() + WEEK_IN_SECONDS,
					),
				),
				'ttl'      => HOUR_IN_SECONDS,
			),
		);
	}

	/**
	 * Returns the location the handler saves for the default API response.
	 *
	 * The API's IP is replaced with the anonymized IP sent in the request.
	 *
	 * @return array
	 */
	private function get_expected_location(): array {
		$location       = $this->api_response['body']['location'];
		$location['ip'] = '203.0.113.0';

		return $location;
	}

	/**
	 * @ticket 65252
	 */
	public function test_get_community_events_searches_location(): void {
		// The client IP is unchanged, so only the search should replace the saved location.
		update_user_meta(
			self::$admin_id,
			'community-events-location',
			array(
				'description' => 'Lyon',
				'ip'          => '203.0.113.0',
			)
		);

		$_POST = array(
			'_ajax_nonce' => wp_create_nonce( 'community_events' ),
			'location'    => 'Paris',
			'timezone'    => 'Europe/Paris',
		);

		$response = json_decode( $this->get_ajax_response( 'get-community-events' ), true );

		$this->assertTrue( $response['success'] );
		$this->assertSame( $this->get_expected_location(), $response['data']['location'] );
		$this->assertSame( array( 'Paris Meetup', 'WordCamp Paris' ), wp_list_pluck( $response['data']['events'], 'title' ), 'Expired events should be removed.' );
		$this->assertSame( $this->get_expected_location(), get_user_meta( self::$admin_id, 'community-events-location', true ) );

		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'api.wordpress.org', wp_parse_url( $this->requests[0]['url'], PHP_URL_HOST ) );
		$this->assertSame( '/events/1.0/', wp_parse_url( $this->requests[0]['url'], PHP_URL_PATH ) );
		$this->assertSame( 'Paris', $this->requests[0]['args']['body']['location'] );
		$this->assertSame( 'Europe/Paris', $this->requests[0]['args']['body']['timezone'] );
		$this->assertSame( '203.0.113.0', $this->requests[0]['args']['body']['ip'] );
	}

	/**
	 * Without a location search, the saved location is only replaced when the client IP changes.
	 *
	 * @ticket 65252
	 *
	 * @dataProvider data_saved_location_without_search
	 *
	 * @param array|null $saved_location Location saved before the request, or null for none.
	 * @param bool       $response_ip    Whether the API response includes an IP.
	 * @param bool       $should_update  Whether the saved location should be replaced.
	 */
	public function test_get_community_events_updates_saved_location_when_ip_changes( ?array $saved_location, bool $response_ip, bool $should_update ): void {
		if ( null !== $saved_location ) {
			update_user_meta( self::$admin_id, 'community-events-location', $saved_location );
		}

		if ( ! $response_ip ) {
			unset( $this->api_response['body']['location']['ip'] );
		}

		$_POST = array(
			'_ajax_nonce' => wp_create_nonce( 'community_events' ),
		);

		$response = json_decode( $this->get_ajax_response( 'get-community-events' ), true );

		$this->assertTrue( $response['success'] );
		$this->assertCount( 1, $this->requests );

		if ( $should_update ) {
			$expected = $this->get_expected_location();
		} else {
			$expected = $saved_location ?? '';
		}

		$this->assertSame( $expected, get_user_meta( self::$admin_id, 'community-events-location', true ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ 0: array|null, 1: bool, 2: bool }>
	 */
	public static function data_saved_location_without_search(): array {
		$saved_location = array(
			'description' => 'Lyon',
			'latitude'    => '45.764',
			'longitude'   => '4.836',
			'country'     => 'FR',
		);

		return array(
			'no saved location'               => array( null, true, true ),
			'saved location without an IP'    => array( $saved_location, true, true ),
			'saved IP differs from client IP' => array( array_merge( $saved_location, array( 'ip' => '198.51.100.0' ) ), true, true ),
			'saved IP matches client IP'      => array( array_merge( $saved_location, array( 'ip' => '203.0.113.0' ) ), true, false ),
			'API response has no IP'          => array( null, false, false ),
		);
	}

	/**
	 * @ticket 65252
	 */
	public function test_get_community_events_returns_error_for_api_error_code(): void {
		$this->api_response = array(
			'code' => 500,
			'body' => array(),
		);

		$_POST = array(
			'_ajax_nonce' => wp_create_nonce( 'community_events' ),
			'location'    => 'Paris',
		);

		$this->assertSame(
			array(
				'success' => false,
				'data'    => array( 'error' => 'Invalid API response code (500).' ),
			),
			json_decode( $this->get_ajax_response( 'get-community-events' ), true )
		);
		$this->assertSame( '', get_user_meta( self::$admin_id, 'community-events-location', true ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_get_community_events_returns_error_for_failed_request(): void {
		$this->api_response = new WP_Error( 'http_request_failed', 'Mock connection failure.' );

		$_POST = array(
			'_ajax_nonce' => wp_create_nonce( 'community_events' ),
			'location'    => 'Paris',
		);

		$this->assertSame(
			array(
				'success' => false,
				'data'    => array( 'error' => 'Mock connection failure.' ),
			),
			json_decode( $this->get_ajax_response( 'get-community-events' ), true )
		);
		$this->assertSame( '', get_user_meta( self::$admin_id, 'community-events-location', true ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_get_community_events_rejects_invalid_nonce(): void {
		$_POST = array(
			'_ajax_nonce' => 'invalid-nonce',
			'location'    => 'Paris',
		);

		$this->assertSame( '-1', $this->get_ajax_response( 'get-community-events' ) );
		$this->assertCount( 0, $this->requests, 'No API request should be made.' );
		$this->assertSame( '', get_user_meta( self::$admin_id, 'community-events-location', true ) );
	}
}
