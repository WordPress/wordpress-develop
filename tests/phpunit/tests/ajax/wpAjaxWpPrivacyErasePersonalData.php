<?php
/**
 * Testing Ajax handler for erasing personal data.
 *
 * @package WordPress\UnitTests
 * @since 5.2.0
 */

/**
 * Tests_Ajax_PrivacyExportPersonalData class.
 *
 * @since 5.2.0
 *
 * @group ajax
 * @group privacy
 *
 * @covers ::wp_ajax_wp_privacy_erase_personal_data
 */
class Tests_Ajax_wpAjaxWpPrivacyErasePersonalData extends WP_Ajax_UnitTestCase {

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
	 * Eraser Index.
	 *
	 * @since 5.2.0
	 *
	 * @var int $eraser
	 */
	protected static $eraser;

	/**
	 * Eraser Key.
	 *
	 * @since 5.2.0
	 *
	 * @var string $eraser_key
	 */
	protected static $eraser_key;

	/**
	 * Eraser Friendly Name.
	 *
	 * @since 5.2.0
	 *
	 * @var string $eraser_friendly_name
	 */
	protected static $eraser_friendly_name;

	/**
	 * Page Index.
	 *
	 * @since 5.2.0
	 *
	 * @var int $page
	 */
	protected static $page;

	/**
	 * Last response parsed.
	 *
	 * @since 5.2.0
	 *
	 * @var array $_last_response_parsed
	 */
	protected $_last_response_parsed;

	/**
	 * An array key in the test eraser to unset.
	 *
	 * @since 5.2.0
	 *
	 * @var string $key_to_unset
	 */
	protected $key_to_unset;

	/**
	 * A value to change the test eraser callback to.
	 *
	 * @since 5.2.0
	 *
	 * @var string $new_callback_value
	 */
	protected $new_callback_value;

	/**
	 * Create user erase request fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$request_email        = 'requester@example.com';
		self::$request_id           = wp_create_user_request( self::$request_email, 'remove_personal_data' );
		self::$action               = 'wp-privacy-erase-personal-data';
		self::$eraser               = 1;
		self::$eraser_key           = 'custom-eraser';
		self::$eraser_friendly_name = 'Custom Eraser';
		self::$page                 = 1;
	}

	/**
	 * Register a custom personal data eraser.
	 */
	public function set_up() {
		parent::set_up();

		$this->key_to_unset = '';

		// Make sure the erasers response is not modified and avoid sending emails.
		remove_all_filters( 'wp_privacy_personal_data_erasure_page' );
		remove_all_actions( 'wp_privacy_personal_data_erased' );

		// Only use our custom privacy personal data eraser.
		remove_all_filters( 'wp_privacy_personal_data_erasers' );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_custom_personal_data_eraser' ) );

		$this->_setRole( 'administrator' );
		// `erase_others_personal_data` meta cap in Multisite installation is only granted to those with `manage_network` capability.
		if ( is_multisite() ) {
			grant_super_admin( get_current_user_id() );
		}
	}

	/**
	 * Clean up after each test method.
	 */
	public function tear_down() {
		remove_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_custom_personal_data_eraser' ) );
		$this->new_callback_value = '';

		if ( is_multisite() ) {
			revoke_super_admin( get_current_user_id() );
		}

		parent::tear_down();
	}

	/**
	 * Helper method for changing the test eraser's callback function.
	 *
	 * @param string|array $callback New test eraser callback index value.
	 */
	protected function _set_eraser_callback( $callback ) {
		$this->new_callback_value = $callback;
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'filter_eraser_callback_value' ), 20 );
	}

	/**
	 * Change the test eraser callback to a specified value.
	 *
	 * @since 5.2.0
	 *
	 * @param array $erasers List of data erasers.
	 *
	 * @return array Array of data erasers.
	 */
	public function filter_eraser_callback_value( $erasers ) {
		$erasers[ self::$eraser_key ]['callback'] = $this->new_callback_value;

		return $erasers;
	}

	/**
	 * Helper method for unsetting an array index in the test eraser.
	 *
	 * @param string|bool $key Test eraser key to unset.
	 */
	protected function _unset_eraser_key( $key ) {
		$this->key_to_unset = $key;
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'filter_unset_eraser_index' ), 20 );
	}

	/**
	 * Unsets an array key in the test eraser.
	 *
	 * If the key is false, the eraser is set to false.
	 *
	 * @since 5.2.0
	 *
	 * @param array $erasers Erasers.
	 *
	 * @return array Erasers.
	 */
	public function filter_unset_eraser_index( $erasers ) {
		if ( false === $this->key_to_unset ) {
			$erasers[ self::$eraser_key ] = false;
		} elseif ( ! empty( $this->key_to_unset ) ) {
			unset( $erasers[ self::$eraser_key ][ $this->key_to_unset ] );
		}

		return $erasers;
	}

	/**
	 * Helper method for erasing a key from the eraser response.
	 *
	 * @since 5.2.0
	 *
	 * @param array $key Response key to unset.
	 */
	protected function _unset_response_key( $key ) {
		$this->key_to_unset = $key;
		$this->_set_eraser_callback( array( $this, 'filter_unset_response_index' ) );
	}

	/**
	 * Unsets an array index in a response.
	 *
	 * @since 5.2.0
	 *
	 * @param string $email_address The requester's email address.
	 * @param int    $page          Page number.
	 *
	 * @return array Export data.
	 */
	public function filter_unset_response_index( $email_address, $page = 1 ) {
		$response = $this->callback_personal_data_eraser( $email_address, $page );

		if ( ! empty( $this->key_to_unset ) ) {
			unset( $response[ $this->key_to_unset ] );
		}

		return $response;
	}

	/**
	 * The function should send an error when the request ID is missing.
	 *
	 * @since 5.2.0
	 *
	 * @ticket 43438
	 */
	public function test_error_when_missing_request_id() {
		$this->assertNotWPError( self::$request_id );

		// Set up a request.
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
	 *
	 * @ticket 43438
	 */
	public function test_error_when_request_id_invalid() {
		$this->assertNotWPError( self::$request_id );

		// Set up a request.
		$this->_make_ajax_call(
			array(
				'id' => -1, // Invalid request ID.
			)
		);

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame( 'Invalid request ID.', $this->_last_response_parsed['data'] );
	}

	/**
	 * The function should send an error when the current user is missing required capabilities.
	 *
	 * @since 5.2.0
	 *
	 * @ticket 43438
	 */
	public function test_error_when_current_user_missing_required_capabilities() {
		$this->_setRole( 'author' );

		$this->assertFalse( current_user_can( 'erase_others_personal_data' ) );
		$this->assertFalse( current_user_can( 'delete_users' ) );

		$this->_make_ajax_call();

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame( 'Sorry, you are not allowed to perform this action.', $this->_last_response_parsed['data'] );
	}

	/**
	 * Test requests do not succeed on multisite when the current user is not a network admin.
	 *
	 * @ticket 43438
	 * @group multisite
	 * @group ms-required
	 */
	public function test_error_when_current_user_missing_required_capabilities_multisite() {
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
			'export-request@example.com',
			'export_personal_data' // Incorrect request type, expects 'remove_personal_data'.
		);

		$this->_make_ajax_call(
			array(
				'security' => wp_create_nonce( 'wp-privacy-erase-personal-data-' . $request_id ),
				'id'       => $request_id,
			)
		);

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame( 'Invalid request type.', $this->_last_response_parsed['data'] );
	}

	/**
	 * The function should send an error when the request email is invalid.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_invalid_email() {
		wp_update_post(
			array(
				'ID'         => self::$request_id,
				'post_title' => '', // Invalid requester's email address.
			)
		);

		$this->_make_ajax_call();

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame( 'Invalid email address in request.', $this->_last_response_parsed['data'] );
	}

	/**
	 * The function should send an error when the eraser index is missing.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_missing_eraser_index() {
		$this->_make_ajax_call(
			array(
				'eraser' => null, // Missing eraser index.
			)
		);

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame( 'Missing eraser index.', $this->_last_response_parsed['data'] );
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
	 * The function should send an error when the eraser index is negative.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_negative_eraser_index() {
		$this->_make_ajax_call(
			array(
				'eraser' => -1, // Negative eraser index.
			)
		);

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame( 'Eraser index cannot be less than one.', $this->_last_response_parsed['data'] );
	}

	/**
	 * The function should send an error when the eraser index is out of range.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_eraser_index_out_of_range() {
		$this->_make_ajax_call(
			array(
				'eraser' => PHP_INT_MAX, // Out of range eraser index.
			)
		);

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame( 'Eraser index is out of range.', $this->_last_response_parsed['data'] );
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
	 * The function should send an error when an eraser is not an array.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_eraser_not_array() {
		$this->_unset_eraser_key( false );
		$this->_make_ajax_call();

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame(
			sprintf(
				'Expected an array describing the eraser at index %s.',
				self::$eraser
			),
			$this->_last_response_parsed['data']
		);
	}

	/**
	 * The function should send an error when an eraser is missing a friendly name.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_eraser_missing_friendly_name() {
		$this->_unset_eraser_key( 'eraser_friendly_name' );
		$this->_make_ajax_call();

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame(
			sprintf(
				'Eraser array at index %s does not include a friendly name.',
				self::$eraser
			),
			$this->_last_response_parsed['data']
		);
	}

	/**
	 * The function should send an error when an eraser is missing a callback.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_eraser_missing_callback() {
		$this->_unset_eraser_key( 'callback' );
		$this->_make_ajax_call();

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame(
			sprintf(
				'Eraser does not include a callback: %s.',
				self::$eraser_friendly_name
			),
			$this->_last_response_parsed['data']
		);
	}

	/**
	 * The function should send an error when an eraser, at a given index, has an invalid callback.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_eraser_index_invalid_callback() {
		$this->_set_eraser_callback( false );
		$this->_make_ajax_call();

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame(
			sprintf(
				'Eraser callback is not valid: %s.',
				self::$eraser_friendly_name
			),
			$this->_last_response_parsed['data']
		);
	}

	/**
	 * The function should send an error when an eraser, at a given index, is missing an array response.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_eraser_index_invalid_response() {
		$this->_set_eraser_callback( '__return_null' );
		$this->_make_ajax_call();

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame(
			sprintf(
				'Did not receive array from %1$s eraser (index %2$d).',
				self::$eraser_friendly_name,
				self::$eraser
			),
			$this->_last_response_parsed['data']
		);
	}

	/**
	 * The function should send an error when missing an items_removed index.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_eraser_items_removed_missing() {
		$this->_unset_response_key( 'items_removed' );
		$this->_make_ajax_call();

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame(
			sprintf(
				'Expected items_removed key in response array from %1$s eraser (index %2$d).',
				self::$eraser_friendly_name,
				self::$eraser
			),
			$this->_last_response_parsed['data']
		);
	}

	/**
	 * The function should send an error when missing an items_retained index.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_eraser_items_retained_missing() {
		$this->_unset_response_key( 'items_retained' );
		$this->_make_ajax_call();

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame(
			sprintf(
				'Expected items_retained key in response array from %1$s eraser (index %2$d).',
				self::$eraser_friendly_name,
				self::$eraser
			),
			$this->_last_response_parsed['data']
		);
	}

	/**
	 * The function should send an error when missing a messages index.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_eraser_messages_missing() {
		$this->_unset_response_key( 'messages' );
		$this->_make_ajax_call();

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame(
			sprintf(
				'Expected messages key in response array from %1$s eraser (index %2$d).',
				self::$eraser_friendly_name,
				self::$eraser
			),
			$this->_last_response_parsed['data']
		);
	}

	/**
	 * The function should send an error when the messages index is not an array.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_eraser_messages_not_array() {
		$this->_set_eraser_callback( array( $this, 'filter_response_messages_invalid' ) );
		$this->_make_ajax_call();

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame(
			sprintf(
				'Expected messages key to reference an array in response array from %1$s eraser (index %2$d).',
				self::$eraser_friendly_name,
				self::$eraser
			),
			$this->_last_response_parsed['data']
		);
	}

	/**
	 * Change the messages index to an invalid value (not an array).
	 *
	 * @since 5.2.0
	 *
	 * @param string $email_address The requester's email address.
	 * @param int    $page          Page number.
	 *
	 * @return array Export data.
	 */
	public function filter_response_messages_invalid( $email_address, $page = 1 ) {
		$response             = $this->callback_personal_data_eraser( $email_address, $page );
		$response['messages'] = true;

		return $response;
	}

	/**
	 * The function should send an error when an eraser is missing 'done' in array response.
	 *
	 * @since 5.2.0
	 */
	public function test_error_when_eraser_missing_done_response() {
		$this->_unset_response_key( 'done' );
		$this->_make_ajax_call();

		$this->assertFalse( $this->_last_response_parsed['success'] );
		$this->assertSame(
			sprintf(
				'Expected done flag in response array from %1$s eraser (index %2$d).',
				self::$eraser_friendly_name,
				self::$eraser
			),
			$this->_last_response_parsed['data']
		);
	}

	/**
	 * The function should successfully send erasers response data when the current user has the required
	 * capabilities.
	 *
	 * @since 5.2.0
	 *
	 * @ticket 43438
	 */
	public function test_success_when_current_user_has_required_capabilities() {
		$this->assertTrue( current_user_can( 'erase_others_personal_data' ) );
		$this->assertTrue( current_user_can( 'delete_users' ) );

		$this->_make_ajax_call();

		$this->assertSame(
			sprintf( 'A message regarding retained data for %s.', self::$request_email ),
			$this->_last_response_parsed['data']['messages'][0]
		);
		$this->assertTrue( $this->_last_response_parsed['success'] );
		$this->assertTrue( $this->_last_response_parsed['data']['items_removed'] );
		$this->assertTrue( $this->_last_response_parsed['data']['items_retained'] );
		$this->assertTrue( $this->_last_response_parsed['data']['done'] );
	}

	/**
	 * The function should successfully send erasers response data when no items to erase.
	 *
	 * @since 5.2.0
	 *
	 * @ticket 43438
	 */
	public function test_success_when_no_items_to_erase() {

		$this->_make_ajax_call( array( 'page' => 2 ) );

		$this->assertTrue( $this->_last_response_parsed['success'] );
		$this->assertFalse( $this->_last_response_parsed['data']['items_removed'] );
		$this->assertFalse( $this->_last_response_parsed['data']['items_retained'] );
		$this->assertEmpty( $this->_last_response_parsed['data']['messages'] );
		$this->assertTrue( $this->_last_response_parsed['data']['done'] );
	}

	/**
	 * Test that the function's output should be filterable with the `wp_privacy_personal_data_erasure_page` filter.
	 *
	 * @since 5.2.0
	 */
	public function test_output_should_be_filterable() {
		add_filter( 'wp_privacy_personal_data_erasure_page', array( $this, 'filter_eraser_data_response' ), 20, 6 );
		$this->_make_ajax_call();

		$expected_new_index = self::$request_email . '-' . self::$request_id . '-' . self::$eraser_key;

		$this->assertTrue( $this->_last_response_parsed['success'] );
		$this->assertSame( 'filtered removed', $this->_last_response_parsed['data']['items_removed'] );
		$this->assertSame( 'filtered retained', $this->_last_response_parsed['data']['items_retained'] );
		$this->assertSame( array( 'filtered messages' ), $this->_last_response_parsed['data']['messages'] );
		$this->assertSame( 'filtered done', $this->_last_response_parsed['data']['done'] );
		$this->assertSame( $expected_new_index, $this->_last_response_parsed['data']['new_index'] );
	}

	/**
	 * Filters the eraser response.
	 *
	 * @since 5.2.0
	 *
	 * @param array  $response        The personal data for the given eraser and page.
	 * @param int    $eraser_index    The index of the eraser that provided this data.
	 * @param string $email_address   The email address associated with this personal data.
	 * @param int    $page            The page for this response.
	 * @param int    $request_id      The privacy request post ID associated with this request.
	 * @param string $eraser_key      The key (slug) of the eraser that provided this data.
	 *
	 * @return array Filtered erase response.
	 */
	public function filter_eraser_data_response( $response, $eraser_index, $email_address, $page, $request_id, $eraser_key ) {
		$response['items_removed']  = 'filtered removed';
		$response['items_retained'] = 'filtered retained';
		$response['messages']       = array( 'filtered messages' );
		$response['done']           = 'filtered done';
		$response['new_index']      = $email_address . '-' . $request_id . '-' . $eraser_key;

		return $response;
	}

	/**
	 * Register handler for a custom personal data eraser.
	 *
	 * @since 5.2.0
	 *
	 * @param array $erasers An array of personal data erasers.
	 *
	 * @return array An array of personal data erasers.
	 */
	public function register_custom_personal_data_eraser( $erasers ) {
		$erasers[ self::$eraser_key ] = array(
			'eraser_friendly_name' => self::$eraser_friendly_name,
			'callback'             => array( $this, 'callback_personal_data_eraser' ),
		);
		return $erasers;
	}

	/**
	 * Custom Personal Data Eraser.
	 *
	 * @since 5.2.0
	 *
	 * @param  string $email_address The comment author email address.
	 * @param  int    $page          Page number.
	 *
	 * @return array Erase data.
	 */
	public function callback_personal_data_eraser( $email_address, $page = 1 ) {
		if ( 1 === $page ) {
			return array(
				'items_removed'  => true,
				'items_retained' => true,
				'messages'       => array( sprintf( 'A message regarding retained data for %s.', $email_address ) ),
				'done'           => true,
			);
		}

		return array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
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
			'action'   => self::$action,
			'security' => wp_create_nonce( self::$action . '-' . self::$request_id ),
			'page'     => self::$page,
			'id'       => self::$request_id,
			'eraser'   => self::$eraser,
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
