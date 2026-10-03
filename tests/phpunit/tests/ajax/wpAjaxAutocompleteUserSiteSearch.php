<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Tests for the `site_search` type of the wp_ajax_autocomplete_user() AJAX handler.
 *
 * Unlike the `add` and `search` types, `site_search` works on single-site and only
 * requires the `list_users` capability, so these tests are not `ms-required`.
 *
 * @package    WordPress
 * @subpackage UnitTests
 *
 * @group ajax
 * @group user
 *
 * @ticket 19867
 *
 * @covers ::wp_ajax_autocomplete_user
 */
class Tests_Ajax_wpAjaxAutocompleteUserSiteSearch extends WP_Ajax_UnitTestCase {

	/**
	 * An administrator (has both list_users and edit_users).
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * An editor granted list_users but lacking edit_users, used for the
	 * email-privacy assertions.
	 *
	 * @var int
	 */
	protected static $lister_id;

	/**
	 * A subscriber (cannot list users).
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * The user the queries are expected to match.
	 *
	 * @var int
	 */
	protected static $target_id;

	/**
	 * Create shared fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Factory instance.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );

		self::$lister_id = $factory->user->create( array( 'role' => 'editor' ) );
		get_userdata( self::$lister_id )->add_cap( 'list_users' );

		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );

		self::$target_id = $factory->user->create(
			array(
				'role'         => 'author',
				'user_login'   => 'targetuser',
				'display_name' => 'Target Person',
				'user_email'   => 'targetuser@example.org',
			)
		);

		// On multisite, the edit_users capability is reserved for super admins.
		if ( is_multisite() ) {
			grant_super_admin( self::$admin_id );
		}
	}

	/**
	 * Reset request superglobals between tests.
	 */
	public function set_up() {
		parent::set_up();
		$_REQUEST = array();
		$_GET     = array();
	}

	/**
	 * Runs the Ajax handler and returns the response passed to wp_die().
	 *
	 * @return string The raw response.
	 */
	protected function handle_site_search(): string {
		try {
			$this->_handleAjax( 'autocomplete-user' );
		} catch ( WPAjaxDieStopException $e ) {
			return $e->getMessage();
		}

		$this->fail( 'wp_ajax_autocomplete_user() did not stop execution.' );
	}

	/**
	 * Populates $_GET for a site_search request.
	 *
	 * @param array $args Extra request arguments.
	 */
	protected function set_request( array $args = array() ) {
		$_GET = wp_slash(
			array_merge(
				array(
					'autocomplete_type' => 'site_search',
					'term'              => 'targetuser',
				),
				$args
			)
		);
	}

	/**
	 * A user without list_users is denied.
	 *
	 * @ticket 19867
	 */
	public function test_site_search_requires_list_users_capability() {
		wp_set_current_user( self::$subscriber_id );
		$this->set_request();

		$this->assertSame( '-1', $this->handle_site_search() );
	}

	/**
	 * A user with list_users but not edit_users may search.
	 *
	 * @ticket 19867
	 */
	public function test_site_search_allowed_with_list_users_capability() {
		wp_set_current_user( self::$lister_id );
		$this->set_request();

		$response = json_decode( $this->handle_site_search(), true );

		$this->assertIsArray( $response );
		$this->assertNotEmpty( $response, 'The matching user should be returned.' );
		$this->assertSame( 'targetuser', $response[0]['value'], 'The default value field is the user login.' );
	}

	/**
	 * A missing or non-string term returns 0.
	 *
	 * @ticket 19867
	 */
	public function test_missing_term_returns_zero() {
		wp_set_current_user( self::$admin_id );
		$_GET = wp_slash( array( 'autocomplete_type' => 'site_search' ) );

		$this->assertSame( '0', $this->handle_site_search() );
	}

	/**
	 * A term consisting only of asterisks returns 0 rather than matching all users.
	 *
	 * @ticket 19867
	 */
	public function test_asterisk_only_term_returns_zero() {
		wp_set_current_user( self::$admin_id );
		$this->set_request( array( 'term' => '***' ) );

		$this->assertSame( '0', $this->handle_site_search() );
	}

	/**
	 * The user_id field returns the numeric ID as the value.
	 *
	 * @ticket 19867
	 */
	public function test_user_id_field_returns_numeric_id() {
		wp_set_current_user( self::$admin_id );
		$this->set_request( array( 'autocomplete_field' => 'user_id' ) );

		$response = json_decode( $this->handle_site_search(), true );

		$this->assertNotEmpty( $response );
		$this->assertSame( self::$target_id, $response[0]['value'] );
	}

	/**
	 * The user_email field returns the email for a user who can edit others.
	 *
	 * @ticket 19867
	 */
	public function test_user_email_field_returned_for_edit_users() {
		wp_set_current_user( self::$admin_id );
		$this->set_request( array( 'autocomplete_field' => 'user_email' ) );

		$response = json_decode( $this->handle_site_search(), true );

		$this->assertNotEmpty( $response );
		$this->assertSame( 'targetuser@example.org', $response[0]['value'] );
	}

	/**
	 * The user_email field is denied to a user who cannot edit others; the value
	 * falls back to the user login.
	 *
	 * @ticket 19867
	 */
	public function test_user_email_field_denied_without_edit_users() {
		wp_set_current_user( self::$lister_id );
		$this->set_request( array( 'autocomplete_field' => 'user_email' ) );

		$response = json_decode( $this->handle_site_search(), true );

		$this->assertNotEmpty( $response );
		$this->assertSame( 'targetuser', $response[0]['value'] );
	}

	/**
	 * A label template is expanded from the matched user's fields.
	 *
	 * @ticket 19867
	 */
	public function test_label_template_is_expanded() {
		wp_set_current_user( self::$admin_id );
		$this->set_request( array( 'autocomplete_label' => '{{display_name}} ({{user_login}})' ) );

		$response = json_decode( $this->handle_site_search(), true );

		$this->assertNotEmpty( $response );
		$this->assertSame( 'Target Person (targetuser)', $response[0]['label'] );
	}

	/**
	 * The email token is stripped from the label for a user who cannot edit others.
	 *
	 * @ticket 19867
	 */
	public function test_email_token_stripped_without_edit_users() {
		wp_set_current_user( self::$lister_id );
		$this->set_request( array( 'autocomplete_label' => '{{display_name}} {{user_email}}' ) );

		$response = json_decode( $this->handle_site_search(), true );

		$this->assertNotEmpty( $response );
		$this->assertStringNotContainsString( 'targetuser@example.org', $response[0]['label'] );
	}

	/**
	 * Without a template, the default label is the display name and never the email.
	 *
	 * @ticket 19867
	 */
	public function test_default_label_is_display_name() {
		wp_set_current_user( self::$lister_id );
		$this->set_request();

		$response = json_decode( $this->handle_site_search(), true );

		$this->assertNotEmpty( $response );
		$this->assertSame( 'Target Person', $response[0]['label'] );
	}

	/**
	 * A user who cannot edit others cannot find a user by email alone.
	 *
	 * @ticket 19867
	 */
	public function test_email_not_searchable_without_edit_users() {
		wp_set_current_user( self::$lister_id );
		$this->set_request( array( 'term' => 'targetuser@example.org' ) );

		$response = json_decode( $this->handle_site_search(), true );

		$this->assertSame( array(), $response, 'No user should be found by email without edit_users.' );
	}

	/**
	 * A user who can edit others can find a user by email.
	 *
	 * @ticket 19867
	 */
	public function test_email_searchable_with_edit_users() {
		wp_set_current_user( self::$admin_id );
		$this->set_request( array( 'term' => 'targetuser@example.org' ) );

		$response = json_decode( $this->handle_site_search(), true );

		$this->assertNotEmpty( $response );
		$this->assertSame( 'targetuser', $response[0]['value'] );
	}

	/**
	 * Results are capped at 20 users.
	 *
	 * @ticket 19867
	 */
	public function test_results_are_capped_at_twenty() {
		for ( $i = 0; $i < 25; $i++ ) {
			self::factory()->user->create(
				array(
					'role'       => 'subscriber',
					'user_login' => "capuser{$i}",
				)
			);
		}

		wp_set_current_user( self::$admin_id );
		$this->set_request( array( 'term' => 'capuser' ) );

		$response = json_decode( $this->handle_site_search(), true );

		$this->assertIsArray( $response );
		$this->assertLessThanOrEqual( 20, count( $response ) );
	}
}
