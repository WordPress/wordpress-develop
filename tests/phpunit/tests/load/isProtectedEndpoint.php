<?php

/**
 * Tests for is_protected_endpoint().
 *
 * @group load
 *
 * @covers ::is_protected_endpoint
 */
class Tests_Load_IsProtectedEndpoint extends WP_UnitTestCase {

	/**
	 * Original $pagenow value.
	 *
	 * @var mixed
	 */
	private $original_pagenow;

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

		$this->original_pagenow        = $GLOBALS['pagenow'] ?? null;
		$this->had_request_action      = array_key_exists( 'action', $_REQUEST );
		$this->original_request_action = $this->had_request_action ? $_REQUEST['action'] : null;
	}

	public function tear_down() {
		if ( null !== $this->original_pagenow ) {
			$GLOBALS['pagenow'] = $this->original_pagenow;
		} else {
			unset( $GLOBALS['pagenow'] );
		}

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
	public function test_returns_false_for_front_end_request() {
		$this->assertFalse( is_protected_endpoint() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_true_for_admin_request() {
		set_current_screen( 'dashboard' );

		$this->assertTrue( is_protected_endpoint() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_can_protect_additional_endpoints() {
		add_filter( 'is_protected_endpoint', '__return_true' );

		$this->assertTrue( is_protected_endpoint() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_does_not_run_for_already_protected_endpoints() {
		$GLOBALS['pagenow'] = 'wp-login.php';

		$filter = new MockAction();
		add_filter( 'is_protected_endpoint', array( $filter, 'filter' ) );

		$this->assertTrue( is_protected_endpoint() );
		$this->assertSame( 0, $filter->get_call_count() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_true_for_protected_ajax_action() {
		add_filter( 'wp_doing_ajax', '__return_true' );
		$_REQUEST['action'] = 'heartbeat';

		$this->assertTrue( is_protected_endpoint() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_false_for_unprotected_ajax_action() {
		add_filter( 'wp_doing_ajax', '__return_true' );
		$_REQUEST['action'] = 'custom-action';

		$this->assertFalse( is_protected_endpoint() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_admin_ajax_without_protected_action_is_not_protected() {
		add_filter( 'wp_doing_ajax', '__return_true' );
		set_current_screen( 'dashboard' );
		$_REQUEST['action'] = 'custom-action';

		$this->assertFalse( is_protected_endpoint() );
	}
}
