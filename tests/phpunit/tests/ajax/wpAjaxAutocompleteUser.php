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
	 */
	protected static int $super_admin_id;

	/**
	 * An administrator of the current site.
	 */
	protected static int $site_admin_id;

	/**
	 * A user without the 'promote_users' capability.
	 */
	protected static int $subscriber_id;

	/**
	 * The user expected to be found by the autocomplete queries.
	 */
	protected static int $target_user_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$super_admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$site_admin_id  = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id  = $factory->user->create( array( 'role' => 'subscriber' ) );
		self::$target_user_id = $factory->user->create(
			array(
				'role'       => 'subscriber',
				'user_login' => 'autocompleteuser',
				'user_email' => 'autocompleteuser+bat\'leth@klingon.example.org',
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
	protected function handle_autocomplete_user(): string {
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

		$_GET = wp_slash(
			array(
				'autocomplete_type' => 'search',
				'term'              => 'autocompleteuser',
			)
		);

		$response = json_decode( $this->handle_autocomplete_user(), true );

		$this->assertIsArray( $response, 'The response should be a JSON encoded array.' );
		$this->assertCount( 1, $response, 'Only the matching user should be returned.' );
		$result = array_first( $response );
		$this->assertIsArray( $result );
		$this->assertSame( 'autocompleteuser', $result['value'], 'The user login should be returned as the value.' );
		$this->assertIsString( $result['label'] );
		$this->assertStringContainsString( 'autocompleteuser+bat\'leth@klingon.example.org', $result['label'], 'The label should contain the email address.' );
	}

	/**
	 * Tests that the email address is returned when it is the requested field.
	 *
	 * @ticket 65051
	 */
	public function test_should_return_the_email_address_as_the_value_when_requested() {
		wp_set_current_user( self::$super_admin_id );

		$_GET = wp_slash(
			array(
				'autocomplete_type'  => 'search',
				'autocomplete_field' => 'user_email',
				'term'               => 'autocompleteuser',
			)
		);

		$response = json_decode( $this->handle_autocomplete_user(), true );

		$this->assertIsArray( $response, 'The response should be a JSON encoded array.' );
		$this->assertCount( 1, $response, 'Only the matching user should be returned.' );
		$result = array_first( $response );
		$this->assertIsArray( $result );
		$this->assertSame( 'autocompleteuser+bat\'leth@klingon.example.org', $result['value'], 'The email address should be returned as the value.' );
	}

	/**
	 * Tests that users of the current site are excluded when adding a user to it.
	 *
	 * @ticket 65051
	 */
	public function test_should_exclude_users_of_the_current_site_when_adding() {
		wp_set_current_user( self::$super_admin_id );

		// The default autocomplete type is 'add', which excludes existing users of the site.
		$_GET = wp_slash(
			array(
				'term' => 'autocompleteuser',
			)
		);

		$response = json_decode( $this->handle_autocomplete_user(), true );

		$this->assertSame( array(), $response, 'A user of the current site should not be suggested.' );
	}

	/**
	 * Tests that HTML tags are removed from the search term.
	 *
	 * @ticket 65051
	 *
	 * @dataProvider data_terms_containing_tags
	 *
	 * @param string $term Term containing HTML tags.
	 */
	public function test_should_strip_tags_from_the_search_term( string $term ) {
		wp_set_current_user( self::$super_admin_id );

		$_GET = wp_slash(
			array(
				'autocomplete_type' => 'search',
				'term'              => $term,
			)
		);

		$search = null;
		add_action(
			'pre_get_users',
			static function ( WP_User_Query $query ) use ( &$search ) {
				$search = $query->get( 'search' );
			}
		);

		$response = json_decode( $this->handle_autocomplete_user(), true );
		$this->assertIsArray( $response, 'The response should be a JSON encoded array.' );

		$this->assertSame( '*autocompleteuser*', $search, 'The search term should be sanitized before it is passed to get_users().' );
		$this->assertCount( 1, $response, 'The sanitized term should still match the user.' );
	}

	/**
	 * Data provider.
	 *
	 * Note that `wp_strip_all_tags()` removes script and style elements along
	 * with their contents, while for other tags only the tags themselves are
	 * removed.
	 *
	 * @return array<string, array{string}>
	 */
	public static function data_terms_containing_tags(): array {
		return array(
			'script element after the term' => array( 'autocompleteuser<script>alert(1)</script>' ),
			'tags wrapping the term'        => array( '<b>autocompleteuser</b>' ),
		);
	}

	/**
	 * Tests that searching for an email address with apostrophes is successful.
	 *
	 * @ticket 65051
	 */
	public function test_search_email_address_with_apostrophe() {
		wp_set_current_user( self::$super_admin_id );

		$_GET = wp_slash(
			array(
				'autocomplete_type'  => 'search',
				'autocomplete_field' => 'user_email',
				'term'               => 'autocompleteuser+bat\'leth@klingon.example.org',
			)
		);

		$response = json_decode( $this->handle_autocomplete_user(), true );

		$this->assertIsArray( $response, 'The response should be a JSON encoded array.' );
		$this->assertCount( 1, $response, 'Only the matching user should be returned.' );
		$result = array_first( $response );
		$this->assertIsArray( $result );
		$this->assertSame( 'autocompleteuser+bat\'leth@klingon.example.org', $result['value'], 'The email address should be returned as the value.' );
	}

	/**
	 * Tests that a missing search term does not return results.
	 *
	 * @ticket 65051
	 */
	public function test_missing_term_does_not_return_results() {
		wp_set_current_user( self::$super_admin_id );

		$_GET = wp_slash(
			array(
				'autocomplete_type' => 'search',
			)
		);

		$this->assertSame( '0', $this->handle_autocomplete_user() );
	}

	/**
	 * Tests that an empty search term does not return results.
	 *
	 * @ticket 65051
	 *
	 * @dataProvider data_empty_terms
	 *
	 * @param string $term Empty or whitespace-only term.
	 */
	public function test_empty_term_does_not_return_results( string $term ) {
		wp_set_current_user( self::$super_admin_id );

		$_GET = wp_slash(
			array(
				'autocomplete_type' => 'search',
				'term'              => $term,
			)
		);

		$this->assertSame( '0', $this->handle_autocomplete_user() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{string}>
	 */
	public static function data_empty_terms(): array {
		return array(
			'empty string'    => array( '' ),
			'whitespace only' => array( '   ' ),
		);
	}

	/**
	 * Tests that a non-string search term does not return results.
	 *
	 * @ticket 65051
	 */
	public function test_non_string_term_does_not_return_results() {
		wp_set_current_user( self::$super_admin_id );

		$_GET = wp_slash(
			array(
				'autocomplete_type' => 'search',
				'term'              => array( 'autocompleteuser' ),
			)
		);

		$this->assertSame( '0', $this->handle_autocomplete_user() );
	}

	/**
	 * Tests that a term consisting only of asterisks does not match all users.
	 *
	 * @ticket 65051
	 */
	public function test_asterisk_only_term_does_not_return_results() {
		wp_set_current_user( self::$super_admin_id );

		$_GET = wp_slash(
			array(
				'autocomplete_type' => 'search',
				'term'              => '**',
			)
		);

		$this->assertSame( '0', $this->handle_autocomplete_user() );
	}

	/**
	 * Tests that a term wrapped in asterisks still matches.
	 *
	 * @ticket 65051
	 */
	public function test_asterisk_wrapped_term_returns_results() {
		wp_set_current_user( self::$super_admin_id );

		$_GET = wp_slash(
			array(
				'autocomplete_type' => 'search',
				'term'              => '*autocompleteuser*',
			)
		);

		$response = json_decode( $this->handle_autocomplete_user(), true );

		$this->assertIsArray( $response, 'The response should be a JSON encoded array.' );
		$this->assertCount( 1, $response, 'The matching user should be returned.' );
	}

	/**
	 * Tests that users without the 'promote_users' capability are denied.
	 *
	 * @ticket 65051
	 */
	public function test_should_deny_users_without_the_promote_users_capability() {
		wp_set_current_user( self::$subscriber_id );

		$_GET = wp_slash(
			array(
				'autocomplete_type' => 'search',
				'term'              => 'autocompleteuser',
			)
		);

		$this->assertSame( '-1', $this->handle_autocomplete_user() );
	}

	/**
	 * Tests that site administrators are denied unless the filter allows them.
	 *
	 * @ticket 65051
	 */
	public function test_should_deny_site_administrators_by_default() {
		wp_set_current_user( self::$site_admin_id );

		$_GET = wp_slash(
			array(
				'autocomplete_type' => 'search',
				'term'              => 'autocompleteuser',
			)
		);

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

		$_GET = wp_slash(
			array(
				'autocomplete_type' => 'search',
				'term'              => 'autocompleteuser',
			)
		);

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

		$_GET = wp_slash(
			array(
				'autocomplete_type' => 'search',
				'term'              => 'autocompleteuser',
			)
		);

		$this->assertSame( '-1', $this->handle_autocomplete_user() );
	}
}
