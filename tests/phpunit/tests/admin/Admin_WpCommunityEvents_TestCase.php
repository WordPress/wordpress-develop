<?php

abstract class Admin_WpCommunityEvents_TestCase extends WP_UnitTestCase {

	/**
	 * An instance of the class to test.
	 *
	 * @since 4.8.0
	 *
	 * @var WP_Community_Events
	 */
	protected $instance;

	/**
	 * Performs setup tasks before the first test is run.
	 *
	 * @since 5.9.0
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-community-events.php';
	}

	/**
	 * Performs setup tasks for every test.
	 *
	 * @since 4.8.0
	 */
	public function set_up() {
		parent::set_up();

		$this->instance = new WP_Community_Events( 1, $this->get_user_location() );
	}

	/**
	 * Simulates a stored user location.
	 *
	 * @access private
	 * @since 4.8.0
	 *
	 * @return array The mock location.
	 */
	protected function get_user_location() {
		return array(
			'description' => 'San Francisco',
			'latitude'    => '37.7749300',
			'longitude'   => '-122.4194200',
			'country'     => 'US',
		);
	}

	/**
	 * Simulates an HTTP response with a non-200 response code.
	 *
	 * @since 4.8.0
	 *
	 * @return array A mock response with a 404 HTTP status code
	 */
	public function _http_request_bad_response_code() {
		return array(
			'headers'  => '',
			'body'     => '',
			'response' => array(
				'code' => 404,
			),
			'cookies'  => '',
			'filename' => '',
		);
	}

	/**
	 * Simulates an HTTP response with a body that does not have the required properties.
	 *
	 * @since 4.8.0
	 *
	 * @return array A mock response that's missing required properties.
	 */
	public function _http_request_invalid_response() {
		return array(
			'headers'  => '',
			'body'     => wp_json_encode( array() ),
			'response' => array(
				'code' => 200,
			),
			'cookies'  => '',
			'filename' => '',
		);
	}

	/**
	 * Simulates an HTTP response with valid location and event data.
	 *
	 * @since 4.8.0
	 *
	 * @return array A mock HTTP response with valid data.
	 */
	public function _http_request_valid_response() {
		return array(
			'headers'  => '',
			'body'     => wp_json_encode(
				array(
					'location' => $this->get_user_location(),
					'events'   => $this->get_valid_events(),
				)
			),
			'response' => array(
				'code' => 200,
			),
			'cookies'  => '',
			'filename' => '',
		);
	}

	/**
	 * Get a sample of valid events.
	 *
	 * @return array[]
	 */
	protected function get_valid_events() {
		return array(
			array(
				'type'                 => 'meetup',
				'title'                => 'Flexbox + CSS Grid: Magic for Responsive Layouts',
				'url'                  => 'https://www.meetup.com/Eastbay-WordPress-Meetup/events/236031233/',
				'meetup'               => 'The East Bay WordPress Meetup Group',
				'meetup_url'           => 'https://www.meetup.com/Eastbay-WordPress-Meetup/',
				'start_unix_timestamp' => strtotime( 'next Sunday 1pm' ),
				'end_unix_timestamp'   => strtotime( 'next Sunday 2pm' ),

				'location'             => array(
					'location'  => 'Oakland, CA, USA',
					'country'   => 'us',
					'latitude'  => 37.808453,
					'longitude' => -122.26593,
				),
			),

			array(
				'type'                 => 'meetup',
				'title'                => 'Part 3- Site Maintenance - Tools to Make It Easy',
				'url'                  => 'https://www.meetup.com/Wordpress-Bay-Area-CA-Foothills/events/237706839/',
				'meetup'               => 'WordPress Bay Area Foothills Group',
				'meetup_url'           => 'https://www.meetup.com/Wordpress-Bay-Area-CA-Foothills/',
				'start_unix_timestamp' => strtotime( 'next Wednesday 1:30pm' ),
				'end_unix_timestamp'   => strtotime( 'next Wednesday 2:30pm' ),

				'location'             => array(
					'location'  => 'Milpitas, CA, USA',
					'country'   => 'us',
					'latitude'  => 37.432813,
					'longitude' => -121.907095,
				),
			),

			array(
				'type'                 => 'wordcamp',
				'title'                => 'WordCamp San Francisco',
				'url'                  => 'https://sf.wordcamp.org/2020/',
				'meetup'               => null,
				'meetup_url'           => null,
				'start_unix_timestamp' => strtotime( 'next Saturday' ),
				'end_unix_timestamp'   => strtotime( 'next Saturday 8pm' ),

				'location'             => array(
					'location'  => 'San Francisco, CA',
					'country'   => 'US',
					'latitude'  => 37.432813,
					'longitude' => -121.907095,
				),
			),
		);
	}
}
