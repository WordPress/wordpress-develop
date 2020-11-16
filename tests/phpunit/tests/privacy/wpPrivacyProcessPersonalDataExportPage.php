<?php
/**
 * Test cases for the `wp_privacy_process_personal_data_export_page()` function.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 5.2.0
 */

/**
 * Tests_Privacy_WpPrivacyProcessPersonalDataExportPage class.
 *
 * @group privacy
 * @covers ::wp_privacy_process_personal_data_export_page
 *
 * @since 5.2.0
 */
class Tests_Privacy_WpPrivacyProcessPersonalDataExportPage extends WP_UnitTestCase {
	/**
	 * Request ID.
	 *
	 * @since 5.2.0
	 *
	 * @var int $request_id
	 */
	protected static $request_id;

	/**
	 * Response for the First Page.
	 *
	 * @since 5.2.0
	 *
	 * @var array $response
	 */
	protected static $response_first_page;

	/**
	 * Response for the Last Page.
	 *
	 * @since 5.2.0
	 *
	 * @var array $response_last_page
	 */
	protected static $response_last_page;

	/**
	 * Exports URL.
	 *
	 * @since 5.5.0
	 *
	 * @var string $exports_url
	 */
	protected static $exports_url;

	/**
	 * Export File Name.
	 *
	 * @since 5.5.0
	 *
	 * @var string $export_file_name
	 */
	protected static $export_file_name;

	/**
	 * Export File URL.
	 *
	 * @since 5.5.0
	 *
	 * @var string $export_file_url
	 */
	protected static $export_file_url;

	/**
	 * Requester Email.
	 *
	 * @since 5.2.0
	 *
	 * @var string $requester_email
	 */
	protected static $requester_email;

	/**
	 * Index Of The First Page.
	 *
	 * @since 5.2.0
	 *
	 * @var int $page
	 */
	protected static $page_index_first;

	/**
	 * Index Of The Last Page.
	 *
	 * @since 5.2.0
	 *
	 * @var int $page_index_last
	 */
	protected static $page_index_last;

	/**
	 * Index of the First Exporter.
	 *
	 * @since 5.2.0
	 *
	 * @var int $exporter_index_first
	 */
	protected static $exporter_index_first;

	/**
	 * Index of the Last Exporter.
	 *
	 * @since 5.2.0
	 *
	 * @var int $exporter_index_last
	 */
	protected static $exporter_index_last;

	/**
	 * Key of the First Exporter.
	 *
	 * @since 5.2.0
	 *
	 * @var int $exporter_key_first
	 */
	protected static $exporter_key_first;

	/**
	 * Key of the Last Exporter.
	 *
	 * @since 5.2.0
	 *
	 * @var int $exporter_key_last
	 */
	protected static $exporter_key_last;

	/**
	 * Export data stored on the `wp_privacy_personal_data_export_file` action hook.
	 *
	 * @var string $_export_data_grouped_fetched_within_callback
	 */
	public $_export_data_grouped_fetched_within_callback;

	/**
	 * Create user request fixtures shared by test methods.
	 *
	 * @since 5.2.0
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$requester_email      = 'requester@example.com';
		self::$exports_url          = wp_privacy_exports_url();
		self::$export_file_name     = 'wp-personal-data-file-Wv0RfMnGIkl4CFEDEEkSeIdfLmaUrLsl.zip';
		self::$export_file_url      = self::$exports_url . self::$export_file_name;
		self::$request_id           = wp_create_user_request( self::$requester_email, 'export_personal_data' );
		self::$page_index_first     = 1;
		self::$page_index_last      = 2;
		self::$exporter_index_first = 1;
		self::$exporter_index_last  = 2;
		self::$exporter_key_first   = 'custom-exporter-first';
		self::$exporter_key_last    = 'custom-exporter-last';

		$data = array(
			array(
				'group_id'          => 'custom-exporter-group-id',
				'group_label'       => 'Custom Exporter Group Label',
				'group_description' => 'Custom Exporter Group Description',
				'item_id'           => 'custom-exporter-item-id',
				'data'              => array(
					array(
						'name'  => 'Email',
						'value' => self::$requester_email,
					),
				),
			),
		);

		self::$response_first_page = array(
			'done' => false,
			'data' => $data,
		);

		self::$response_last_page = array(
			'done' => true,
			'data' => $data,
		);
	}

	/**
	 * Setup before each test method.
	 *
	 * @since 5.2.0
	 */
	public function setUp() {
		parent::setUp();

		// Avoid writing export files to disk. Using `WP_Filesystem_MockFS` is blocked by #44204.
		remove_action( 'wp_privacy_personal_data_export_file', 'wp_privacy_generate_personal_data_export_file', 10 );

		// Register our custom data exporters, very late, so we can override other unrelated exporters.
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'filter_register_custom_personal_data_exporters' ), 9999 );

		// Set Ajax context for `wp_send_json()` and `wp_die()`.
		add_filter( 'wp_doing_ajax', '__return_true' );

		// Set up a `wp_die()` ajax handler that throws an exception, to be able to get
		// the error message from `wp_send_json_error( 'some message here' )`,
		// called by `wp_privacy_process_personal_data_export_page()`.
		add_filter( 'wp_die_ajax_handler', array( $this, 'get_wp_die_handler' ), 1, 1 );

		// Suppress warnings from "Cannot modify header information - headers already sent by".
		$this->_error_level = error_reporting();
		error_reporting( $this->_error_level & ~E_WARNING );
	}

	/**
	 * Clean up after each test method.
	 *
	 * @since 5.2.0
	 */
	public function tearDown() {
		error_reporting( $this->_error_level );

		parent::tearDown();
	}

	/**
	 * Filter to register custom personal data exporters.
	 *
	 * @since 5.2.0
	 *
	 * @param  array $exporters An array of personal data exporters.
	 * @return array An array of personal data exporters.
	 */
	public function filter_register_custom_personal_data_exporters( $exporters ) {
		// Let's override other unrelated exporters.
		$exporters = array();

		$exporters[ self::$exporter_key_first ] = array(
			'exporter_friendly_name' => __( 'Custom Exporter #1' ),
			'callback'               => null,
		);
		$exporters[ self::$exporter_key_last ]  = array(
			'exporter_friendly_name' => __( 'Custom Exporter #2' ),
			'callback'               => null,
		);

		return $exporters;
	}

	/**
	 * Set up a test method to properly assert an exception.
	 *
	 * @since 5.2.0
	 *
	 * @param string $expected_output The expected string exception output.
	 */
	private function _setup_expected_failure( $expected_output ) {
		$this->setExpectedException( 'WPDieException' );
		$this->expectOutputString( $expected_output );
	}

	/**
	 * Ensure the correct errors are returned when exporter responses are incorrect.
	 *
	 * @ticket 44233
	 *
	 * @dataProvider data_wp_privacy_process_personal_data_export_page
	 *
	 * @param string|array $expected_response The response from the personal data exporter for the given test.
	 */
	public function test_wp_privacy_process_personal_data_export_page( $expected_response ) {
		$actual_response = wp_privacy_process_personal_data_export_page(
			$expected_response,
			self::$exporter_index_last,
			self::$requester_email,
			self::$page_index_last,
			self::$request_id,
			true,
			self::$exporter_key_last
		);

		$this->assertSame( $expected_response, $actual_response );
	}

	/**
	 * Provide test cases for `test_wp_privacy_process_personal_data_export_page()`.
	 *
	 * @since 5.2.0
	 *
	 * @return array {
	 *     @type array {
	 *         @type string|array $response The response from the personal data exporter to test. Can be a string or an array.
	 *     }
	 * }
	 */
	public function data_wp_privacy_process_personal_data_export_page() {
		return array(
			// Response is not an array.
			array(
				'not-an-array',
			),
			// Missing `done` array key.
			array(
				array(
					'missing-done-array-key' => true,
				),
			),
			// Missing `data` array key.
			array(
				array(
					'done' => true,
				),
			),
			// `data` key is not an array.
			array(
				array(
					'done' => true,
					'data' => 'not-an-array',
				),
			),
			array(
				array(
					'done' => true,
					'data' => array(),
				),
			),
		);
	}

	/**
	 * Provide test scenarios for both sending and not sending an email.
	 *
	 * @since 5.2.0
	 *
	 * @return array {
	 *     @type array {
	 *         @type bool $send_as_email Whether the final results of the export should be emailed to the user.
	 *     }
	 * }
	 */
	public function data_send_as_email_options() {
		return array(
			array(
				true,
			),
			array(
				false,
			),
		);
	}

	/**
	 * The function should send a JSON error when receiving an invalid request ID.
	 *
	 * @ticket 44233
	 *
	 * @dataProvider data_send_as_email_options
	 *
	 * @param bool Whether the final results of the export should be emailed to the user.
	 */
	public function test_send_error_when_invalid_request_id( $send_as_email ) {
		$response           = array(
			'done' => true,
			'data' => array(),
		);
		$invalid_request_id = 0;

		// Process data, given the last exporter, on the last page and send as email.
		$this->_setup_expected_failure( '{"success":false,"data":"Invalid request ID when merging exporter data."}' );

		wp_privacy_process_personal_data_export_page(
			$response,
			self::$exporter_index_last,
			self::$requester_email,
			self::$page_index_last,
			$invalid_request_id,
			$send_as_email,
			self::$exporter_key_last
		);
	}

	/**
	 * The function should send a JSON error when the request has an invalid action name.
	 *
	 * @ticket 44233
	 *
	 * @dataProvider data_send_as_email_options
	 *
	 * @param bool Whether the final results of the export should be emailed to the user.
	 */
	public function test_send_error_when_invalid_request_action_name( $send_as_email ) {
		$response = array(
			'done' => true,
			'data' => array(),
		);

		// Create a valid request ID, but for a different action than the function expects.
		$request_id = wp_create_user_request( self::$requester_email, 'remove_personal_data' );

		// Process data, given the last exporter, on the last page and send as email.
		$this->_setup_expected_failure( '{"success":false,"data":"Invalid request ID when merging exporter data."}' );

		wp_privacy_process_personal_data_export_page(
			$response,
			self::$exporter_index_last,
			self::$requester_email,
			self::$page_index_last,
			$request_id,
			$send_as_email,
			self::$exporter_key_last
		);
	}

	/**
	 * The function should store export raw data until the export finishes. Then the meta key should be deleted.
	 *
	 * @ticket 44233
	 *
	 * @dataProvider data_send_as_email_options
	 *
	 * @param bool Whether the final results of the export should be emailed to the user.
	 *
	 */
	public function test_raw_data_post_meta( $send_as_email ) {
		$this->assertEmpty( get_post_meta( self::$request_id, '_export_data_raw', true ) );

		// Adds post meta when processing data, given the first exporter on the first page and send as email.
		wp_privacy_process_personal_data_export_page(
			self::$response_first_page,
			self::$exporter_index_first,
			self::$requester_email,
			self::$page_index_first,
			self::$request_id,
			$send_as_email,
			self::$exporter_key_first
		);

		$this->assertNotEmpty( get_post_meta( self::$request_id, '_export_data_raw', true ) );

		// Deletes post meta when processing data, given the last exporter on the last page and send as email.
		wp_privacy_process_personal_data_export_page(
			self::$response_last_page,
			self::$exporter_index_last,
			self::$requester_email,
			self::$page_index_last,
			self::$request_id,
			$send_as_email,
			self::$exporter_key_last
		);

		$this->assertEmpty( get_post_meta( self::$request_id, '_export_data_raw', true ) );
	}

	/**
	 * The function should add `_export_data_grouped` post meta for the request, only available
	 * when personal data export file is generated.
	 *
	 * @ticket 44233
	 *
	 * @dataProvider data_send_as_email_options
	 *
	 * @param bool Whether the final results of the export should be emailed to the user.
	 */
	public function test_add_post_meta_with_groups_data_only_available_when_export_file_generated( $send_as_email ) {
		// Adds post meta when processing data, given the first exporter on the first page and send as email.
		wp_privacy_process_personal_data_export_page(
			self::$response_first_page,
			self::$exporter_index_first,
			self::$requester_email,
			self::$page_index_first,
			self::$request_id,
			true,
			self::$exporter_key_first
		);
		$this->assertEmpty( get_post_meta( self::$request_id, '_export_data_grouped', true ) );

		add_action( 'wp_privacy_personal_data_export_file', array( $this, 'action_callback_to_get_export_groups_data' ) );

		// Process data, given the last exporter on the last page and send as email.
		wp_privacy_process_personal_data_export_page(
			self::$response_last_page,
			self::$exporter_index_last,
			self::$requester_email,
			self::$page_index_last,
			self::$request_id,
			true,
			self::$exporter_key_last
		);

		$this->assertNotEmpty( $this->_export_data_grouped_fetched_within_callback );
		$this->assertEmpty( get_post_meta( self::$request_id, '_export_data_grouped', true ) );
	}

	/**
	 * When mail delivery fails, the function should send a JSON error on the last page of the last exporter.
	 *
	 * @ticket 44233
	 */
	public function test_send_error_on_last_page_of_last_exporter_when_mail_delivery_fails() {
		// Cause `wp_mail()` to return false, to simulate mail delivery failure. Filter removed in tearDown.
		add_filter( 'wp_mail_from', '__return_empty_string' );

		// Process data, given the last exporter, on the last page and send as email.
		$this->_setup_expected_failure( '{"success":false,"data":"Unable to send personal data export email."}' );

		wp_privacy_process_personal_data_export_page(
			self::$response_last_page,
			self::$exporter_index_last,
			self::$requester_email,
			self::$page_index_last,
			self::$request_id,
			true,
			self::$exporter_key_last
		);
	}

	/**
	 * The function should return the response, containing the export file URL, when not sent as email
	 * for the last exporter on the last page.
	 *
	 * @ticket 44233
	 */
	public function test_return_response_with_export_file_url_when_not_sent_as_email_for_last_exporter_on_last_page() {
		update_post_meta( self::$request_id, '_export_file_name', self::$export_file_name );

		// Process data, given the last exporter, on the last page and not send as email.
		$actual_response = wp_privacy_process_personal_data_export_page(
			self::$response_last_page,
			self::$exporter_index_last,
			self::$requester_email,
			self::$page_index_last,
			self::$request_id,
			false,
			self::$exporter_key_last
		);

		$this->assertArrayHasKey( 'url', $actual_response );
		$this->assertSame( self::$export_file_url, $actual_response['url'] );
		$this->assertSame( self::$response_last_page['done'], $actual_response['done'] );
		$this->assertSame( self::$response_last_page['data'], $actual_response['data'] );
	}

	/**
	 * The function should return the response, not containing the export file URL, when sent as email
	 * for the last exporter on the last page.
	 *
	 * @ticket 44233
	 */
	public function test_return_response_without_export_file_url_when_sent_as_email_for_last_exporter_on_last_page() {
		update_post_meta( self::$request_id, '_export_file_name', self::$export_file_name );

		// Process data, given the last exporter, on the last page and send as email.
		$actual_response = wp_privacy_process_personal_data_export_page(
			self::$response_last_page,
			self::$exporter_index_last,
			self::$requester_email,
			self::$page_index_last,
			self::$request_id,
			true,
			self::$exporter_key_last
		);

		$this->assertArrayNotHasKey( 'url', $actual_response );
		$this->assertSame( self::$response_last_page['done'], $actual_response['done'] );
		$this->assertSame( self::$response_last_page['data'], $actual_response['data'] );
	}

	/**
	 * Test that request statuses are properly transitioned.
	 *
	 * @ticket 44233
	 *
	 * @dataProvider data_export_page_status_transitions
	 *
	 * @param string $expected_status The expected post status after calling the function.
	 * @param string $response_page   The exporter page to pass. Options are 'first' and 'last'. Default 'first'.
	 * @param string $exporter_index  The exporter index to pass. Options are 'first' and 'last'. Default 'first'.
	 * @param string $page_index      The page index to pass. Options are 'first' and 'last'. Default 'first'.
	 * @param bool   $send_as_email   If the response should be sent as an email.
	 * @param string $exporter_key    The slug (key) of the exporter to pass.
	 */
	public function test_request_status_transitions_correctly( $expected_status, $response_page, $exporter_index, $page_index, $send_as_email, $exporter_key ) {
		if ( 'first' === $response_page ) {
			$response_page = self::$response_first_page;
		} else {
			$response_page = self::$response_last_page;
		}

		if ( 'first' === $exporter_index ) {
			$exporter_index = self::$exporter_index_first;
		} else {
			$exporter_index = self::$exporter_index_last;
		}

		if ( 'first' === $page_index ) {
			$page_index = self::$page_index_first;
		} else {
			$page_index = self::$page_index_last;
		}

		if ( 'first' === $exporter_key ) {
			$exporter_key = self::$exporter_key_first;
		} else {
			$exporter_key = self::$exporter_key_last;
		}

		wp_privacy_process_personal_data_export_page(
			$response_page,
			$exporter_index,
			self::$requester_email,
			$page_index,
			self::$request_id,
			$send_as_email,
			$exporter_key
		);

		$this->assertSame( $expected_status, get_post_status( self::$request_id ) );
	}

	/**
	 * Provide test cases for `test_wp_privacy_process_personal_data_export_page()`.
	 *
	 * @since 5.2.0
	 *
	 * @return array {
	 *     @type array {
	 *         @string string $expected_status The expected post status after calling the function.
	 *         @string string $response_page   The exporter page to pass. Options are 'first' and 'last'. Default 'first'.
	 *         @string string $exporter_index  The exporter index to pass. Options are 'first' and 'last'. Default 'first'.
	 *         @string string $page_index      The page index to pass. Options are 'first' and 'last'. Default 'first'.
	 *         @bool   bool   $send_as_email   If the response should be sent as an email.
	 *         @string string $exporter_key    The slug (key) of the exporter to pass.
	 *     }
	 * }
	 */
	public function data_export_page_status_transitions() {
		return array(
			// Mark the request as completed for the last exporter on the last page, with email.
			array(
				'request-completed',
				'last',
				'last',
				'last',
				true,
				'last',
			),
			// Leave the request as pending for the last exporter on the last page, without email.
			// This check was updated to account for admin vs user export.
			// Don't mark the request as completed when it's an admin download.
			array(
				'request-pending',
				'last',
				'last',
				'last',
				false,
				'last',
			),
			// Leave the request as pending when not the last exporter and not on the last page.
			array(
				'request-pending',
				'first',
				'first',
				'first',
				true,
				'first',
			),
			array(
				'request-pending',
				'first',
				'first',
				'first',
				false,
				'first',
			),
			// Leave the request as pending when last exporter and not on the last page.
			array(
				'request-pending',
				'first',
				'last',
				'first',
				true,
				'last',
			),
			array(
				'request-pending',
				'first',
				'last',
				'first',
				false,
				'last',
			),
			// Leave the request as pending when not last exporter on the last page.
			array(
				'request-pending',
				'last',
				'first',
				'last',
				true,
				'last',
			),
			array(
				'request-pending',
				'last',
				'first',
				'last',
				false,
				'first',
			),
		);
	}

	/**
	 * A callback for the `wp_privacy_personal_data_export_file` action that stores the
	 * `_export_data_grouped` meta data locally for testing.
	 *
	 * @since 5.2.0
	 *
	 * @param int $request_id Request ID.
	 */
	public function action_callback_to_get_export_groups_data( $request_id ) {
		$this->_export_data_grouped_fetched_within_callback = get_post_meta( $request_id, '_export_data_grouped', true );
	}
}
