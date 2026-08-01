<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing Ajax user autocomplete functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 7.1.0
 *
 * @ticket 65051
 *
 * @group ajax
 * @group ms-required
 *
 * @covers ::wp_ajax_autocomplete_user
 */
class Tests_Ajax_wpAjaxAutocompleteUser extends WP_Ajax_UnitTestCase {

	/**
	 * A user with super admin privileges.
	 *
	 * @var int
	 */
	protected static $super_admin_id;

	/**
	 * An administrator of the current site.
	 *
	 * @var int
	 */
	protected static $site_admin_id;

	/**
	 * A user without the 'promote_users' capability.
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * The user expected to be found by the autocomplete queries.
	 *
	 * @var int
	 */
	protected static $target_user_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$super_admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$site_admin_id  = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id  = $factory->user->create( array( 'role' => 'subscriber' ) );
		self::$target_user_id = $factory->user->create(
			array(
				'role'       => 'subscriber',
				'user_login' => 'autocompleteuser',
				'user_email' => 'autocompleteuser@example.org',
			)
		);

		if ( is_multisite() ) {
			grant_super_admin( self::$super_admin_id );
		}
	}

	/**
	 * Runs the Ajax handler and returns the response passed to wp_die().
	 *
	 * The handler never echoes anything, so the response is only available
	 * through the exception thrown by the die handler.
	 *
	 * @return string The raw response.
	 */
	protected function handle_autocomplete_user() {
		try {
			$this->_handleAjax( 'autocomplete-user' );
		} catch ( WPAjaxDieStopException $e ) {
			return $e->getMessage();
		}

		$this->fail( 'wp_ajax_autocomplete_user() did not stop execution.' );
	}

	/**
	 * Tests that users of the current site are returned when searching them.
	 *
	 * @ticket 65051
	 */
	public function test_should_return_users_matching_the_search_term() {
		wp_set_current_user( self::$super_admin_id );

		$_GET['autocomplete_type'] = 'search';
		$_GET['term']              = 'autocompleteuser';

		$response = json_decode( $this->handle_autocomplete_user(), true );

		$this->assertIsArray( $response, 'The response should be a JSON encoded array.' );
		$this->assertCount( 1, $response, 'Only the matching user should be returned.' );
		$this->assertSame( 'autocompleteuser', $response[0]['value'], 'The user login should be returned as the value.' );
		$this->assertStringContainsString( 'autocompleteuser@example.org', $response[0]['label'], 'The label should contain the email address.' );
	}

	/**
	 * Tests that the email address is returned when it is the requested field.
	 *
	 * @ticket 65051
	 */
	public function test_should_return_the_email_address_as_the_value_when_requested() {
		wp_set_current_user( self::$super_admin_id );

		$_GET['autocomplete_type']  = 'search';
		$_GET['autocomplete_field'] = 'user_email';
		$_GET['term']               = 'autocompleteuser';

		$response = json_decode( $this->handle_autocomplete_user(), true );

		$this->assertIsArray( $response, 'The response should be a JSON encoded array.' );
		$this->assertCount( 1, $response, 'Only the matching user should be returned.' );
		$this->assertSame( 'autocompleteuser@example.org', $response[0]['value'], 'The email address should be returned as the value.' );
	}

	/**
	 * Tests that users of the current site are excluded when adding a user to it.
	 *
	 * @ticket 65051
	 */
	public function test_should_exclude_users_of_the_current_site_when_adding() {
		wp_set_current_user( self::$super_admin_id );

		// The default autocomplete type is 'add', which excludes existing users of the site.
		$_GET['term'] = 'autocompleteuser';

		$response = json_decode( $this->handle_autocomplete_user(), true );

		$this->assertSame( array(), $response, 'A user of the current site should not be suggested.' );
	}

	/**
	 * Tests that HTML tags are removed from the search term.
	 *
	 * @ticket 65051
	 */
	public function test_should_strip_tags_from_the_search_term() {
		wp_set_current_user( self::$super_admin_id );

		$_GET['autocomplete_type'] = 'search';
		$_GET['term']              = 'autocompleteuser<script>alert(1)</script>';

		$search = null;
		add_action(
			'pre_get_users',
			static function ( $query ) use ( &$search ) {
				$search = $query->get( 'search' );
			}
		);

		$response = json_decode( $this->handle_autocomplete_user(), true );

		$this->assertSame( '*autocompleteuser*', $search, 'The search term should be sanitized before it is passed to get_users().' );
		$this->assertCount( 1, $response, 'The sanitized term should still match the user.' );
	}

	/**
	 * Tests that a missing search term does not trigger a PHP warning.
	 *
	 * @ticket 65051
	 */
	public function test_should_not_warn_when_the_search_term_is_missing() {
		wp_set_current_user( self::$super_admin_id );

		$_GET['autocomplete_type'] = 'search';

		$warnings = array();
		set_error_handler(
			static function ( $errno, $errstr ) use ( &$warnings ) {
				$warnings[] = $errstr;
				return true;
			},
			E_WARNING
		);

		try {
			$response = json_decode( $this->handle_autocomplete_user(), true );
		} finally {
			restore_error_handler();
		}

		$this->assertSame(
			array(),
			array_values(
				array_filter(
					$warnings,
					static function ( $warning ) {
						return false !== strpos( $warning, 'term' );
					}
				)
			),
			'Accessing a missing search term should not raise a PHP warning.'
		);
		$this->assertIsArray( $response, 'The response should be a JSON encoded array.' );
	}

	/**
	 * Tests that users without the 'promote_users' capability are denied.
	 *
	 * @ticket 65051
	 */
	public function test_should_deny_users_without_the_promote_users_capability() {
		wp_set_current_user( self::$subscriber_id );

		$_GET['autocomplete_type'] = 'search';
		$_GET['term']              = 'autocompleteuser';

		$this->assertSame( '-1', $this->handle_autocomplete_user() );
	}

	/**
	 * Tests that site administrators are denied unless the filter allows them.
	 *
	 * @ticket 65051
	 */
	public function test_should_deny_site_administrators_by_default() {
		wp_set_current_user( self::$site_admin_id );

		$_GET['autocomplete_type'] = 'search';
		$_GET['term']              = 'autocompleteuser';

		$this->assertSame( '-1', $this->handle_autocomplete_user() );
	}

	/**
	 * Tests that site administrators are allowed by the
	 * 'autocomplete_users_for_site_admins' filter.
	 *
	 * @ticket 65051
	 */
	public function test_should_allow_site_administrators_when_filtered() {
		wp_set_current_user( self::$site_admin_id );

		add_filter( 'autocomplete_users_for_site_admins', '__return_true' );

		$_GET['autocomplete_type'] = 'search';
		$_GET['term']              = 'autocompleteuser';

		$response = json_decode( $this->handle_autocomplete_user(), true );

		$this->assertIsArray( $response, 'The response should be a JSON encoded array.' );
		$this->assertCount( 1, $response, 'The matching user should be returned.' );
	}

	/**
	 * Tests that no autocompletion happens on large networks.
	 *
	 * @ticket 65051
	 */
	public function test_should_deny_the_request_on_a_large_network() {
		wp_set_current_user( self::$super_admin_id );

		add_filter( 'wp_is_large_network', '__return_true' );

		$_GET['autocomplete_type'] = 'search';
		$_GET['term']              = 'autocompleteuser';

		$this->assertSame( '-1', $this->handle_autocomplete_user() );
	}
}
