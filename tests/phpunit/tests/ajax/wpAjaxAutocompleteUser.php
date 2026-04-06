<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Tests for the wp_ajax_autocomplete_user() AJAX handler.
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
class Tests_Ajax_wpAjaxAutocompleteUser extends WP_Ajax_UnitTestCase {

	/**
	 * User IDs created for tests.
	 *
	 * @var int[]
	 */
	protected static $user_ids = array();

	/**
	 * Create shared fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_ids['editor'] = $factory->user->create(
			array(
				'role'         => 'editor',
				'user_login'   => 'autocomplete_editor',
				'display_name' => 'Autocomplete Editor',
				'user_email'   => 'autocomplete_editor@example.com',
			)
		);
		// Editors don't have list_users by default. Grant it explicitly so this
		// fixture can reach the search endpoint while still lacking edit_users,
		// which is needed for the email-token stripping tests.
		get_userdata( self::$user_ids['editor'] )->add_cap( 'list_users' );

		self::$user_ids['subscriber']    = $factory->user->create(
			array(
				'role'       => 'subscriber',
				'user_login' => 'autocomplete_subscriber',
			)
		);
		self::$user_ids['administrator'] = $factory->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'autocomplete_admin',
			)
		);
	}

	/**
	 * Reset request superglobals between each test.
	 */
	public function set_up() {
		parent::set_up();
		$_REQUEST = array();
		$_GET     = array();
	}

	// -----------------------------------------------------------------------
	// Permission / capability checks
	// -----------------------------------------------------------------------

	/**
	 * Users without list_users capability should receive -1 (search type).
	 *
	 * @ticket 19867
	 */
	public function test_search_type_requires_list_users_capability() {
		$this->_setRole( 'subscriber' );

		$_GET['term']              = 'autocomplete';
		$_GET['autocomplete_type'] = 'search';

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'autocomplete-user' );
	}

	/**
	 * Users with list_users capability (editor+) should be able to search.
	 *
	 * @ticket 19867
	 */
	public function test_search_type_allowed_for_editor() {
		wp_set_current_user( self::$user_ids['editor'] );

		$_GET['term']              = 'autocomplete';
		$_GET['autocomplete_type'] = 'search';

		try {
			$this->_handleAjax( 'autocomplete-user' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$results = json_decode( $this->_last_response, true );
		$this->assertIsArray( $results );
	}

	/**
	 * Administrator should be able to search.
	 *
	 * @ticket 19867
	 */
	public function test_search_type_allowed_for_administrator() {
		$this->_setRole( 'administrator' );

		$_GET['term']              = 'autocomplete';
		$_GET['autocomplete_type'] = 'search';

		try {
			$this->_handleAjax( 'autocomplete-user' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$results = json_decode( $this->_last_response, true );
		$this->assertIsArray( $results );
	}

	// -----------------------------------------------------------------------
	// Term length validation
	// -----------------------------------------------------------------------

	/**
	 * An empty term should be rejected.
	 *
	 * @ticket 19867
	 */
	public function test_empty_term_is_rejected() {
		$this->_setRole( 'administrator' );

		$_GET['term']              = '';
		$_GET['autocomplete_type'] = 'search';

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'autocomplete-user' );
	}

	/**
	 * A single-character term should be rejected (minimum is 2).
	 *
	 * @ticket 19867
	 */
	public function test_single_char_term_is_rejected() {
		$this->_setRole( 'administrator' );

		$_GET['term']              = 'a';
		$_GET['autocomplete_type'] = 'search';

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'autocomplete-user' );
	}

	/**
	 * The `autocomplete_term_length` filter should allow raising the minimum.
	 *
	 * @ticket 19867
	 */
	public function test_autocomplete_term_length_filter_raises_minimum() {
		$this->_setRole( 'administrator' );

		// Raise minimum to 5.
		add_filter(
			'autocomplete_term_length',
			static function () {
				return 5;
			}
		);

		$_GET['term']              = 'auto'; // 4 chars – below new minimum.
		$_GET['autocomplete_type'] = 'search';

		try {
			$this->_handleAjax( 'autocomplete-user' );
			$this->fail( 'Expected WPAjaxDieStopException was not thrown.' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '-1', $e->getMessage() );
		} finally {
			remove_all_filters( 'autocomplete_term_length' );
		}
	}

	// -----------------------------------------------------------------------
	// Return value / field selection
	// -----------------------------------------------------------------------

	/**
	 * Default field should be user_login when no autocomplete_field is supplied.
	 *
	 * @ticket 19867
	 */
	public function test_default_field_is_user_login() {
		$this->_setRole( 'administrator' );

		$_GET['term']              = 'autocomplete_editor';
		$_GET['autocomplete_type'] = 'search';

		try {
			$this->_handleAjax( 'autocomplete-user' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$results = json_decode( $this->_last_response, true );
		$this->assertNotEmpty( $results );
		$this->assertSame( 'autocomplete_editor', $results[0]['value'] );
	}

	/**
	 * When autocomplete_field=user_id the value should be the numeric user ID.
	 *
	 * @ticket 19867
	 */
	public function test_user_id_field_returns_numeric_id() {
		$this->_setRole( 'administrator' );

		$_GET['term']               = 'autocomplete_editor';
		$_GET['autocomplete_type']  = 'search';
		$_GET['autocomplete_field'] = 'user_id';

		try {
			$this->_handleAjax( 'autocomplete-user' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$results = json_decode( $this->_last_response, true );
		$this->assertNotEmpty( $results );
		$this->assertSame( self::$user_ids['editor'], $results[0]['value'] );
	}

	/**
	 * When autocomplete_field=user_email the value should be the email address.
	 *
	 * @ticket 19867
	 */
	public function test_user_email_field_returns_email() {
		$this->_setRole( 'administrator' );

		$_GET['term']               = 'autocomplete_editor';
		$_GET['autocomplete_type']  = 'search';
		$_GET['autocomplete_field'] = 'user_email';

		try {
			$this->_handleAjax( 'autocomplete-user' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$results = json_decode( $this->_last_response, true );
		$this->assertNotEmpty( $results );
		$this->assertSame( 'autocomplete_editor@example.com', $results[0]['value'] );
	}

	// -----------------------------------------------------------------------
	// Label template tokens
	// -----------------------------------------------------------------------

	/**
	 * The default label template should use {{display_name}}.
	 *
	 * @ticket 19867
	 */
	public function test_default_label_is_display_name() {
		$this->_setRole( 'administrator' );

		$_GET['term']              = 'autocomplete_editor';
		$_GET['autocomplete_type'] = 'search';

		try {
			$this->_handleAjax( 'autocomplete-user' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$results = json_decode( $this->_last_response, true );
		$this->assertNotEmpty( $results );
		$this->assertSame( 'Autocomplete Editor', $results[0]['label'] );
	}

	/**
	 * A custom label template with {{user_login}} should populate correctly.
	 *
	 * @ticket 19867
	 */
	public function test_custom_label_template_with_user_login_token() {
		$this->_setRole( 'administrator' );

		$_GET['term']               = 'autocomplete_editor';
		$_GET['autocomplete_type']  = 'search';
		$_GET['autocomplete_label'] = '{{display_name}} ({{user_login}})';

		try {
			$this->_handleAjax( 'autocomplete-user' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$results = json_decode( $this->_last_response, true );
		$this->assertNotEmpty( $results );
		$this->assertSame( 'Autocomplete Editor (autocomplete_editor)', $results[0]['label'] );
	}

	/**
	 * Users without edit_users capability should have {{user_email}} stripped from label.
	 *
	 * @ticket 19867
	 */
	public function test_email_token_stripped_for_non_edit_users() {
		// This fixture user has list_users but not edit_users.
		wp_set_current_user( self::$user_ids['editor'] );

		$_GET['term']               = 'autocomplete_editor';
		$_GET['autocomplete_type']  = 'search';
		$_GET['autocomplete_label'] = '{{display_name}} - {{user_email}}';

		try {
			$this->_handleAjax( 'autocomplete-user' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$results = json_decode( $this->_last_response, true );
		$this->assertNotEmpty( $results );
		// Email token should be stripped; the raw email should not appear in the label.
		$this->assertStringNotContainsString( '@example.com', $results[0]['label'] );
		// Display name portion should still be present.
		$this->assertStringContainsString( 'Autocomplete Editor', $results[0]['label'] );
	}

	/**
	 * Administrators (edit_users) should see {{user_email}} token in label.
	 *
	 * @ticket 19867
	 */
	public function test_email_token_visible_for_administrator() {
		$this->_setRole( 'administrator' );

		$_GET['term']               = 'autocomplete_editor';
		$_GET['autocomplete_type']  = 'search';
		$_GET['autocomplete_label'] = '{{display_name}} - {{user_email}}';

		try {
			$this->_handleAjax( 'autocomplete-user' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$results = json_decode( $this->_last_response, true );
		$this->assertNotEmpty( $results );
		$this->assertStringContainsString( '@example.com', $results[0]['label'] );
	}

	// -----------------------------------------------------------------------
	// autocomplete_user_results filter
	// -----------------------------------------------------------------------

	/**
	 * The autocomplete_user_results filter should fire and be able to modify results.
	 *
	 * @ticket 19867
	 */
	public function test_autocomplete_user_results_filter_fires() {
		$this->_setRole( 'administrator' );

		$_GET['term']              = 'autocomplete_editor';
		$_GET['autocomplete_type'] = 'search';

		$filter_fired = false;

		$cb = static function ( $results, $term ) use ( &$filter_fired ) {
			$filter_fired = true;
			return $results;
		};

		add_filter( 'autocomplete_user_results', $cb, 10, 2 );

		try {
			$this->_handleAjax( 'autocomplete-user' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		remove_filter( 'autocomplete_user_results', $cb );

		$this->assertTrue( $filter_fired, 'autocomplete_user_results filter should have fired.' );
	}

	/**
	 * The autocomplete_user_results filter should be able to alter the response.
	 *
	 * @ticket 19867
	 */
	public function test_autocomplete_user_results_filter_can_modify_results() {
		$this->_setRole( 'administrator' );

		$_GET['term']              = 'autocomplete_editor';
		$_GET['autocomplete_type'] = 'search';

		add_filter(
			'autocomplete_user_results',
			static function () {
				return array(
					array(
						'label' => 'Overridden',
						'value' => 'overridden',
					),
				);
			}
		);

		try {
			$this->_handleAjax( 'autocomplete-user' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		remove_all_filters( 'autocomplete_user_results' );

		$results = json_decode( $this->_last_response, true );
		$this->assertCount( 1, $results );
		$this->assertSame( 'Overridden', $results[0]['label'] );
	}

	// -----------------------------------------------------------------------
	// Response format
	// -----------------------------------------------------------------------

	/**
	 * Each result must have both 'label' and 'value' keys.
	 *
	 * @ticket 19867
	 */
	public function test_each_result_has_label_and_value_keys() {
		$this->_setRole( 'administrator' );

		$_GET['term']              = 'autocomplete';
		$_GET['autocomplete_type'] = 'search';

		try {
			$this->_handleAjax( 'autocomplete-user' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$results = json_decode( $this->_last_response, true );
		$this->assertNotEmpty( $results );

		foreach ( $results as $result ) {
			$this->assertArrayHasKey( 'label', $result );
			$this->assertArrayHasKey( 'value', $result );
		}
	}

	/**
	 * The result set should be capped (no more than 20 results by default).
	 *
	 * @ticket 19867
	 */
	public function test_results_are_capped_at_twenty() {
		$this->_setRole( 'administrator' );

		// Create 25 users whose login starts with 'resultcap_'.
		for ( $i = 1; $i <= 25; $i++ ) {
			self::factory()->user->create( array( 'user_login' => 'resultcap_' . $i ) );
		}

		$_GET['term']              = 'resultcap_';
		$_GET['autocomplete_type'] = 'search';

		try {
			$this->_handleAjax( 'autocomplete-user' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$results = json_decode( $this->_last_response, true );
		$this->assertLessThanOrEqual( 20, count( $results ) );
	}
}
