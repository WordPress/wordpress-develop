<?php
/**
 * Testing Ajax handler for exporting personal data.
 *
 * @package WordPress\UnitTests
 * @since 5.2.0
 *
 * @group ajax
 * @group privacy
 *
 * @covers ::wp_ajax_wp_privacy_export_personal_data
 */
class Tests_Ajax_wpAjaxWpPrivacyExportPersonalData extends WP_Ajax_UnitTestCase {

	/**
	 * User Request ID.
	 *
	 * @since 5.2.0
	 *
	 * @var int $request_id
	 */
	protected static $request_id;

	/**
	 * User Request Email.
	 *
	 * @since 5.2.0
	 *
	 * @var string $request_email
	 */
	protected static $request_email;

	/**
	 * Ajax Action.
	 *
	 * @since 5.2.0
	 *
	 * @var string $action
	 */
	protected static $action;

	/**
	 * Exporter Index.
	 *
	 * @since 5.2.0
	 *
	 * @var int $exporter
	 */
	protected static $exporter;

	/**
	 * Exporter Key.
	 *
	 * @since 5.2.0
	 *
	 * @var string $exporter_key
	 */
	protected static $exporter_key;

	/**
	 * Exporter Friendly Name.
	 *
	 * @since 5.2.0
	 *
	 * @var string $exporter_friendly_name
	 */
	protected static $exporter_friendly_name;

	/**
	 * Page Index.
	 *
	 * @since 5.2.0
	 *
	 * @var int $page
	 */
	protected static $page;

	/**
	 * Send As Email.
	 *
	 * @since 5.2.0
	 *
	 * @var bool $send_as_email
	 */
	protected static $send_as_email;

	/**
	 * Last response parsed.
	 *
	 * @since 5.2.0
	 *
	 * @var array $_last_response_parsed
	 */
	protected $_last_response_parsed;

	/**
	 * An array key in the test exporter to unset.
	 *
	 * @since 5.2.0
	 *
	 * @var string $key_to_unset
	 */
	protected $key_to_unset;

	/**
	 * A value to change the test exporter callback to.
	 *
	 * @since 5.2.0
	 *
	 * @var string $new_callback_value
	 */
	protected $new_callback_value;

	/**
	 * Create user export request fixtures.
	 *
	 * @since 5.2.0
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$request_email          = 'requester@example.com';
		self::$request_id             = wp_create_user_request( self::$request_email, 'export_personal_data' );
		self::$action                 = 'wp-privacy-export-personal-data';
		self::$exporter               = 1;
		self::$exporter_key           = 'custom-exporter';
		self::$exporter_friendly_name = 'Custom Exporter';
		self::$page                   = 1;
		self::$send_as_email          = false;
	}

	/**
	 * Setup before each test method.
	 *
	 * @since 5.2.0
	 */
	public function set_up() {
		parent::set_up();

		$this->key_to_unset       = '';
		$this->new_callback_value = '';

		// Make sure the exporter response is not modified and avoid e.g. writing export file to disk.
		remove_all_filters( 'wp_privacy_personal_data_export_page' );

		// Only use our custom privacy personal data exporter.
		remove_all_filters( 'wp_privacy_personal_data_exporters' );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'filter_register_custom_personal_data_exporter' ) );

		$this->_setRole( 'administrator' );
		// `export_others_personal_data` meta cap in Multisite installation is only granted to those with `manage_network` capability.
		if ( is_multisite() ) {
			grant_super_admin( get_current_user_id() );
		}
	}

	/**
	 * Clean up after each test method.
	 */
	public function tear_down() {
		remove_filter( 'wp_privacy_personal_data_exporters', array( $this, 'filter_register_custom_personal_data_exporter' ) );

		if ( is_multisite() ) {
			revoke_super_admin( get_current_user_id() );
		}
		parent::tear_down();
	}

	/**
	 * Helper method for changing the test exporter's callback function.
	 *
	 * @param string|array $callback New test exporter callback function.
	 */
	protected function _set_exporter_callback( $callback ) {
		$this->new_callback_value = $callback;
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'filter_exporter_callback_value' ), 20 );
	}

	/**
	 * Change the test exporter callback to a specified value.
	 *
	 * @since 5.2.0
	 *
	 * @param array $exporters List of data exporters.
	 * @return array List of data exporters.
	 */
	public function filter_exporter_callback_value( $exporters ) {
		$exporters[ self::$exporter_key ]['callback'] = $this->new_callback_value;

		return $exporters;
	}

	/**
	 * Helper method for unsetting an array index in the test exporter.
	 *
	 * @param string $key Test exporter key to unset.
	 */
	protected function _unset_exporter_key( $key ) {
		$this->key_to_unset = $key;
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'filter_unset_exporter_key' ), 20 );
	}

	/**
	 * Unset a specified key in the test exporter array.
	 *
	 * @param array $exporters List of data exporters.
	 *
	 * @return array List of data exporters.
	 */
	public function filter_unset_exporter_key( $exporters ) {
		if ( false === $this->key_to_unset ) {
			$exporters[ self::$exporter_key ] = false;
		} elseif ( ! empty( $this->key_to_unset ) ) {
			unset( $exporters[ self::$exporter_key ][ $this->key_to_unset ] );
		}

		return $exporters;
	}

	/**
	 * The function should send an error when the request ID is missing.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_missing_request_id() {
		$this->_make_ajax_call(
			array(
				'id' => null, // Missing request ID.
			)
		);

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame( 'Missing request ID.', $this->_last_response_parsed['data'] );
	}

	/**
	 * The function should send an error when the request ID is less than 1.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_invalid_id() {
		$this->_make_ajax_call(
			array(
				'id' => -1, // Invalid request ID, less than 1.
			)
		);

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame( 'Invalid request ID.', $this->_last_response_parsed['data'] );
	}

	/**
	 * The function should send an error when the current user is missing the required capability.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_current_user_missing_required_capability() {
		$this->_setRole( 'author' );

		$this->_make_ajax_call();

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertFalse( current_user_can( 'export_others_personal_data' ) );
		$this->assertSame( 'Sorry, you are not allowed to perform this action.', $this->_last_response_parsed['data'] );
	}

	/**
	 * Test requests do not succeed on multisite when the current user is not a network admin.
	 *
	 * @ticket 43438
	 * @group multisite
	 * @group ms-required
	 */
	public function test_error_when_current_user_missing_required_capability_multisite() {
		revoke_super_admin( get_current_user_id() );

		$this->_make_ajax_call();

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame( 'Sorry, you are not allowed to perform this action.', $this->_last_response_parsed['data'] );
	}

	/**
	 * The function should send an error when the nonce does not validate.
	 *
	 * @since 5.2.0
	 */
	public function test_failure_with_invalid_nonce() {
		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );

		$this->_make_ajax_call(
			array(
				'security' => 'invalid-nonce',
			)
		);
	}

	/**
	 * The function should send an error when the request type is incorrect.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_incorrect_request_type() {
		$request_id = wp_create_user_request(
			'erase-request@example.com',
			'remove_personal_data' // Incorrect request type, expects 'export_personal_data'.
		);

		$this->_make_ajax_call(
			array(
				'security' => wp_create_nonce( 'wp-privacy-export-personal-data-' . $request_id ),
				'id'       => $request_id,
			)
		);

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame( 'Invalid request type.', $this->_last_response_parsed['data'] );
	}

	/**
	 * The function should send an error when the requester's email address is invalid.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_invalid_email_address() {
		wp_update_post(
			array(
				'ID'         => self::$request_id,
				'post_title' => '', // Invalid requester's email address.
			)
		);

		$this->_make_ajax_call();

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame( 'A valid email address must be given.', $this->_last_response_parsed['data'] );
	}

	/**
	 * The function should send an error when the exporter index is missing.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_missing_exporter_index() {
		$this->_make_ajax_call(
			array(
				'exporter' => null, // Missing exporter index.
			)
		);

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame( 'Missing exporter index.', $this->_last_response_parsed['data'] );
	}

	/**
	 * The function should send an error when the page index is missing.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_missing_page_index() {
		$this->_make_ajax_call(
			array(
				'page' => null, // Missing page index.
			)
		);

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame( 'Missing page index.', $this->_last_response_parsed['data'] );
	}

	/**
	 * The function should send an error when an exporter has improperly used the `wp_privacy_personal_data_exporters` filter.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_exporter_has_improperly_used_exporters_filter() {
		// Improper filter usage: returns false instead of an expected array.
		add_filter( 'wp_privacy_personal_data_exporters', '__return_false', 999 );
		$this->_make_ajax_call();

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame( 'An exporter has improperly used the registration filter.', $this->_last_response_parsed['data'] );
	}

	/**
	 * The function should send an error when the exporter index is negative.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_negative_exporter_index() {
		$this->_make_ajax_call(
			array(
				'exporter' => -1, // Negative exporter index.
			)
		);

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame( 'Exporter index cannot be negative.', $this->_last_response_parsed['data'] );
	}

	/**
	 * The function should send an error when the exporter index is out of range.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_exporter_index_out_of_range() {
		$this->_make_ajax_call(
			array(
				'exporter' => PHP_INT_MAX, // Out of range exporter index.
			)
		);

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame( 'Exporter index is out of range.', $this->_last_response_parsed['data'] );
	}

	/**
	 * The function should send an error when the page index is less than one.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_page_index_less_than_one() {
		$this->_make_ajax_call(
			array(
				'page' => 0, // Page index less than one.
			)
		);

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame( 'Page index cannot be less than one.', $this->_last_response_parsed['data'] );
	}

	/**
	 * The function should send an error when an exporter is not an array.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_exporter_not_array() {
		$this->_unset_exporter_key( false );
		$this->_make_ajax_call();

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame(
			sprintf(
				'Expected an array describing the exporter at index %s.',
				self::$exporter_key
			),
			$this->_last_response_parsed['data']
		);
	}

	/**
	 * The function should send an error when an exporter is missing a friendly name.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_exporter_missing_friendly_name() {
		$this->_unset_exporter_key( 'exporter_friendly_name' );
		$this->_make_ajax_call();

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame(
			sprintf(
				'Exporter array at index %s does not include a friendly name.',
				self::$exporter_key
			),
			$this->_last_response_parsed['data']
		);
	}

	/**
	 * The function should send an error when an exporter is missing a callback.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_exporter_missing_callback() {
		$this->_unset_exporter_key( 'callback' );
		$this->_make_ajax_call();

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame(
			sprintf(
				'Exporter does not include a callback: %s.',
				self::$exporter_friendly_name
			),
			$this->_last_response_parsed['data']
		);
	}

	/**
	 * The function should send an error when an exporter, at a given index, has an invalid callback.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_exporter_index_invalid_callback() {
		$this->_set_exporter_callback( false );
		$this->_make_ajax_call();

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame(
			sprintf(
				'Exporter callback is not a valid callback: %s.',
				self::$exporter_friendly_name
			),
			$this->_last_response_parsed['data']
		);
	}

	/**
	 * When an exporter callback returns a WP_Error, it should be passed as the error.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_exporter_callback_returns_wp_error() {
		$this->_set_exporter_callback( array( $this, 'callback_return_wp_error' ) );
		$this->_make_ajax_call();

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame( 'passed_message', $this->_last_response_parsed['data'][0]['code'] );
		$this->assertSame( 'This is a WP_Error message.', $this->_last_response_parsed['data'][0]['message'] );
	}

	/**
	 * Callback for exporter's response.
	 *
	 * @since 5.2.0
	 *
	 * @param string $email_address The requester's email address.
	 * @param int    $page          Page number.
	 * @return WP_Error WP_Error instance.
	 */
	public function callback_return_wp_error( $email_address, $page = 1 ) {
		return new WP_Error( 'passed_message', 'This is a WP_Error message.' );
	}

	/**
	 * The function should send an error when an exporter, at a given index, is missing an array response.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_exporter_index_invalid_response() {
		$this->_set_exporter_callback( '__return_null' );
		$this->_make_ajax_call();

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame(
			sprintf(
				'Expected response as an array from exporter: %s.',
				self::$exporter_friendly_name
			),
			$this->_last_response_parsed['data']
		);
	}

	/**
	 * The function should send an error when an exporter is missing data in array response.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_exporter_missing_data_response() {
		$this->_set_exporter_callback( array( $this, 'callback_missing_data_response' ) );
		$this->_make_ajax_call();

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame(
			sprintf(
				'Expected data in response array from exporter: %s.',
				self::$exporter_friendly_name
			),
			$this->_last_response_parsed['data']
		);
	}

	/**
	 * Callback for exporter's response.
	 *
	 * @since 5.2.0
	 *
	 * @param string $email_address The requester's email address.
	 * @param int    $page          Page number.
	 *
	 * @return array Export data.
	 */
	public function callback_missing_data_response( $email_address, $page = 1 ) {
		$response = $this->callback_custom_personal_data_exporter( $email_address, $page );
		unset( $response['data'] ); // Missing data part of response.

		return $response;
	}

	/**
	 * The function should send an error when an exporter is missing 'data' array in array response.
	 *
	 * @since 5.2.0
	 */
	public function test_function_should_error_when_exporter_missing_data_array_response() {
		$this->_set_exporter_callback( array( $this, 'callback_missing_data_array_response' ) );
		$this->_make_ajax_call();

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame(
			sprintf(
				'Expected data array in response array from exporter: %s.',
				self::$exporter_friendly_name
			),
			$this->_last_response_parsed['data']
		);
	}

	/**
	 * Callback for exporter's response.
	 *
	 * @since 5.2.0
	 *
	 * @param  string $email_address The requester's email address.
	 * @param  int    $page          Page number.
	 *
	 * @return array Export data.
	 */
	public function callback_missing_data_array_response( $email_address, $page = 1 ) {
		$response         = $this->callback_custom_personal_data_exporter( $email_address, $page );
		$response['data'] = false; // Not an array.
		return $response;
	}

	/**
	 * The function should send an error when an exporter is missing 'done' in array response.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_exporter_missing_done_response() {
		$this->_set_exporter_callback( array( $this, 'callback_missing_done_response' ) );
		$this->_make_ajax_call();

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame(
			sprintf(
				'Expected done (boolean) in response array from exporter: %s.',
				self::$exporter_friendly_name
			),
			$this->_last_response_parsed['data']
		);
	}

	/**
	 * Remove the response's done flag.
	 *
	 * @since 5.2.0
	 *
	 * @param string $email_address The requester's email address.
	 * @param int    $page          Page number.
	 *
	 * @return array Export data.
	 */
	public function callback_missing_done_response( $email_address, $page = 1 ) {
		$response = $this->callback_custom_personal_data_exporter( $email_address, $page );
		unset( $response['done'] );

		return $response;
	}

	/**
	 * The function should successfully send exporter data response when the current user has the required capability.
	 *
	 * @since 5.2.0
	 */
	public function test_succeeds_when_current_user_has_required_capability() {
		$this->assertTrue( current_user_can( 'export_others_personal_data' ) );

		$this->_make_ajax_call();

		$this->assertTrue( $this->_last_response_parsed['success'] );
		$this->assertSame( 'custom-exporter-item-id', $this->_last_response_parsed['data']['data']['item_id'] );
		$this->assertSame( 'Email', $this->_last_response_parsed['data']['data']['data'][0]['name'] );
		$this->assertSame( self::$request_email, $this->_last_response_parsed['data']['data']['data'][0]['value'] );
	}

	/**
	 * The function should successfully send exporter data response when no items to export.
	 *
	 * @since 5.2.0
	 */
	public function test_success_when_no_items_to_export() {

		$this->_make_ajax_call( array( 'page' => 2 ) );

		$this->assertTrue( $this->_last_response_parsed['success'] );
		$this->assertEmpty( $this->_last_response_parsed['data']['data'] );
		$this->assertTrue( $this->_last_response_parsed['data']['done'] );
	}

	/**
	 * The function's output should be filterable with the `wp_privacy_personal_data_export_page` filter.
	 *
	 * @since 5.2.0
	 */
	public function test_output_should_be_filterable() {
		add_filter( 'wp_privacy_personal_data_export_page', array( $this, 'filter_exporter_data_response' ), 20, 7 );
		$this->_make_ajax_call();

		$expected_group_label = sprintf(
			'%s-%s-%s-%s-%s-%s',
			self::$exporter,
			self::$page,
			self::$request_email,
			self::$request_id,
			self::$send_as_email,
			self::$exporter_key
		);

		$this->assertTrue( $this->_last_response_parsed['success'] );
		$this->assertSame( $expected_group_label, $this->_last_response_parsed['data']['group_label'] );
		$this->assertSame( 'filtered_group_id', $this->_last_response_parsed['data']['group_id'] );
		$this->assertSame( 'filtered_item_id', $this->_last_response_parsed['data']['item_id'] );
		$this->assertSame( 'filtered_name', $this->_last_response_parsed['data']['data'][0]['name'] );
		$this->assertSame( 'filtered_value', $this->_last_response_parsed['data']['data'][0]['value'] );
	}

	/**
	 * Filter exporter's data response.
	 *
	 * @since 5.2.0
	 *
	 * @param array  $response        The personal data for the given exporter and page.
	 * @param int    $exporter_index  The index of the exporter that provided this data.
	 * @param string $email_address   The email address associated with this personal data.
	 * @param int    $page            The page for this response.
	 * @param int    $request_id      The privacy request post ID associated with this request.
	 * @param bool   $send_as_email   Whether the final results of the export should be emailed to the user.
	 * @param string $exporter_key    The key (slug) of the exporter that provided this data.
	 *
	 * @return array The personal data for the given exporter and page.
	 */
	public function filter_exporter_data_response( $response, $exporter_index, $email_address, $page, $request_id, $send_as_email, $exporter_key ) {
		$group_label                  = sprintf(
			'%s-%s-%s-%s-%s-%s',
			$exporter_index,
			$page,
			$email_address,
			$request_id,
			$send_as_email,
			$exporter_key
		);
		$response['group_label']      = $group_label;
		$response['group_id']         = 'filtered_group_id';
		$response['item_id']          = 'filtered_item_id';
		$response['data'][0]['name']  = 'filtered_name';
		$response['data'][0]['value'] = 'filtered_value';

		return $response;
	}

	/**
	 * Filter to register a custom personal data exporter.
	 *
	 * @since 5.2.0
	 *
	 * @param array $exporters An array of personal data exporters.
	 *
	 * @return array An array of personal data exporters.
	 */
	public function filter_register_custom_personal_data_exporter( $exporters ) {
		$exporters[ self::$exporter_key ] = array(
			'exporter_friendly_name' => self::$exporter_friendly_name,
			'callback'               => array( $this, 'callback_custom_personal_data_exporter' ),
		);
		return $exporters;
	}

	/**
	 * Callback for a custom personal data exporter.
	 *
	 * @since 5.2.0
	 *
	 * @param string $email_address The requester's email address.
	 * @param int    $page          Page number.
	 *
	 * @return array Export data response.
	 */
	public function callback_custom_personal_data_exporter( $email_address, $page = 1 ) {
		$data_to_export = array();

		if ( 1 === $page ) {
			$data_to_export = array(
				'group_id'    => self::$exporter_key . '-group-id',
				'group_label' => self::$exporter_key . '-group-label',
				'item_id'     => self::$exporter_key . '-item-id',
				'data'        => array(
					array(
						'name'  => 'Email',
						'value' => $email_address,
					),
				),
			);
		}

		return array(
			'data' => $data_to_export,
			'done' => true,
		);
	}

	/**
	 * Helper function for Ajax handler.
	 *
	 * @since 5.2.0
	 *
	 * @param array $args Ajax request arguments.
	 */
	protected function _make_ajax_call( $args = array() ) {
		$this->_last_response_parsed = null;
		$this->_last_response        = '';

		$defaults = array(
			'action'      => self::$action,
			'security'    => wp_create_nonce( self::$action . '-' . self::$request_id ),
			'exporter'    => self::$exporter,
			'page'        => self::$page,
			'sendAsEmail' => self::$send_as_email,
			'id'          => self::$request_id,
		);

		$_POST = wp_parse_args( $args, $defaults );

		try {
			$this->_handleAjax( self::$action );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		if ( $this->_last_response ) {
			$this->_last_response_parsed = json_decode( $this->_last_response, true );
		}
	}
}
