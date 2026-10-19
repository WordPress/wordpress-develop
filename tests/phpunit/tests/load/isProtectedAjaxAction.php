<?php

/**
 * Tests for is_protected_ajax_action().
 *
 * @group load
 *
 * @covers ::is_protected_ajax_action
 */
class Tests_Load_IsProtectedAjaxAction extends WP_UnitTestCase {

	/**
	 * Original $_REQUEST['action'] value.
	 *
	 * @var mixed
	 */
	private $original_request_action;

	/**
	 * Whether $_REQUEST['action'] was set before the test.
	 *
	 * @var bool
	 */
	private $had_request_action;

	public function set_up() {
		parent::set_up();

		$this->had_request_action      = array_key_exists( 'action', $_REQUEST );
		$this->original_request_action = $this->had_request_action ? $_REQUEST['action'] : null;
	}

	public function tear_down() {
		if ( $this->had_request_action ) {
			$_REQUEST['action'] = $this->original_request_action;
		} else {
			unset( $_REQUEST['action'] );
		}

		parent::tear_down();
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_false_when_not_doing_ajax() {
		$_REQUEST['action'] = 'heartbeat';

		$this->assertFalse( is_protected_ajax_action() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_does_not_run_when_not_doing_ajax() {
		$_REQUEST['action'] = 'heartbeat';

		$filter = new MockAction();
		add_filter( 'wp_protected_ajax_actions', array( $filter, 'filter' ) );

		$this->assertFalse( is_protected_ajax_action() );
		$this->assertSame( 0, $filter->get_call_count() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_false_when_doing_ajax_without_action() {
		add_filter( 'wp_doing_ajax', '__return_true' );
		unset( $_REQUEST['action'] );

		$this->assertFalse( is_protected_ajax_action() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_true_for_default_protected_actions() {
		add_filter( 'wp_doing_ajax', '__return_true' );

		$actions = array(
			'edit-theme-plugin-file',
			'heartbeat',
			'install-plugin',
			'install-theme',
			'search-plugins',
			'search-install-plugins',
			'update-plugin',
			'update-theme',
			'activate-plugin',
		);

		foreach ( $actions as $action ) {
			$_REQUEST['action'] = $action;

			$this->assertTrue( is_protected_ajax_action(), $action . ' should be protected.' );
		}
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_false_for_unprotected_ajax_action() {
		add_filter( 'wp_doing_ajax', '__return_true' );
		$_REQUEST['action'] = 'custom-action';

		$this->assertFalse( is_protected_ajax_action() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_can_protect_additional_ajax_actions() {
		add_filter( 'wp_doing_ajax', '__return_true' );
		$_REQUEST['action'] = 'custom-action';

		add_filter(
			'wp_protected_ajax_actions',
			static function ( $actions ) {
				$actions[] = 'custom-action';
				return $actions;
			}
		);

		$this->assertTrue( is_protected_ajax_action() );
	}
}
