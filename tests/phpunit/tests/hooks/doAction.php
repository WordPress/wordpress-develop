<?php

use phpunit\tests\hooks\HooksTrait;

/**
 * Test the do_action method of WP_Hook
 *
 * @group hooks
 * @covers WP_Hook::do_action
 */
class Tests_WP_Hook_Do_Action extends WP_UnitTestCase {
	use HooksTrait;

	private $events        = array();
	private $action_output = '';
	private $hook;

	public function setUp() {
		parent::setUp();
		$this->events = array();
	}

	public function test_do_action_with_callback() {
		$a        = new MockAction();
		$callback = array( $a, 'action' );
		$hook     = $this->setup_hook( __FUNCTION__, $callback, rand( 1, 100 ), rand( 1, 100 ) );
		$arg      = __FUNCTION__ . '_arg';

		$hook->do_action( array( $arg ) );

		$this->assertSame( 1, $a->get_call_count() );
	}

	public function test_do_action_with_multiple_calls() {
		$a        = new MockAction();
		$callback = array( $a, 'filter' );
		$hook     = $this->setup_hook( __FUNCTION__, $callback, rand( 1, 100 ), rand( 1, 100 ) );
		$arg      = __FUNCTION__ . '_arg';

		$hook->do_action( array( $arg ) );
		$hook->do_action( array( $arg ) );

		$this->assertSame( 2, $a->get_call_count() );
	}

	public function test_do_action_with_multiple_callbacks_on_same_priority() {
		$a             = new MockAction();
		$b             = new MockAction();
		$callback_one  = array( $a, 'filter' );
		$callback_two  = array( $b, 'filter' );
		$hook          = new WP_Hook();
		$tag           = __FUNCTION__;
		$priority      = rand( 1, 100 );
		$accepted_args = rand( 1, 100 );
		$arg           = __FUNCTION__ . '_arg';

		$hook->add_filter( $tag, $callback_one, $priority, $accepted_args );
		$hook->add_filter( $tag, $callback_two, $priority, $accepted_args );
		$hook->do_action( array( $arg ) );

		$this->assertSame( 1, $a->get_call_count() );
		$this->assertSame( 1, $a->get_call_count() );
	}

	public function test_do_action_with_multiple_callbacks_on_different_priorities() {
		$a             = new MockAction();
		$b             = new MockAction();
		$callback_one  = array( $a, 'filter' );
		$callback_two  = array( $b, 'filter' );
		$hook          = new WP_Hook();
		$tag           = __FUNCTION__;
		$priority      = rand( 1, 100 );
		$accepted_args = rand( 1, 100 );
		$arg           = __FUNCTION__ . '_arg';

		$hook->add_filter( $tag, $callback_one, $priority, $accepted_args );
		$hook->add_filter( $tag, $callback_two, $priority + 1, $accepted_args );
		$hook->do_action( array( $arg ) );

		$this->assertSame( 1, $a->get_call_count() );
		$this->assertSame( 1, $a->get_call_count() );
	}

	public function test_do_action_with_no_accepted_args() {
		$callback = array( $this, '_action_callback' );
		$hook     = $this->setup_hook( __FUNCTION__, $callback, rand( 1, 100 ), 0 );
		$arg      = __FUNCTION__ . '_arg';

		$hook->do_action( array( $arg ) );

		$this->assertEmpty( $this->events[0]['args'] );
	}

	public function test_do_action_with_one_accepted_arg() {
		$callback = array( $this, '_action_callback' );
		$hook     = $this->setup_hook( __FUNCTION__, $callback, rand( 1, 100 ), 1 );
		$arg      = __FUNCTION__ . '_arg';

		$hook->do_action( array( $arg ) );

		$this->assertCount( 1, $this->events[0]['args'] );
	}

	public function test_do_action_with_more_accepted_args() {
		$callback = array( $this, '_action_callback' );
		$hook     = $this->setup_hook( __FUNCTION__, $callback, rand( 1, 100 ), 100 );
		$arg      = __FUNCTION__ . '_arg';

		$hook->do_action( array( $arg ) );

		$this->assertCount( 1, $this->events[0]['args'] );
	}

	public function test_do_action_doesnt_change_value() {
		$this->hook          = new WP_Hook();
		$this->action_output = '';

		$this->hook->add_filter( 'do_action_doesnt_change_value', array( $this, '_filter_do_action_doesnt_change_value1' ), 10, 1 );
		$this->hook->add_filter( 'do_action_doesnt_change_value', array( $this, '_filter_do_action_doesnt_change_value2' ), 10, 1 );
		$this->hook->add_filter( 'do_action_doesnt_change_value', array( $this, '_filter_do_action_doesnt_change_value3' ), 11, 1 );

		$this->hook->do_action( array( 'a' ) );

		$this->assertSame( 'a1-b1b3-a2a3', $this->action_output );
	}

	public function _filter_do_action_doesnt_change_value1( $value ) {
		$this->action_output .= $value . 1;

		return 'x1';
	}

	public function _filter_do_action_doesnt_change_value2( $value ) {
		$this->hook->remove_filter( 'do_action_doesnt_change_value', array( $this, '_filter_do_action_doesnt_change_value2' ), 10 );

		$this->action_output .= '-';
		$this->hook->do_action( array( 'b' ) );
		$this->action_output .= '-';

		$this->hook->add_filter( 'do_action_doesnt_change_value', array( $this, '_filter_do_action_doesnt_change_value2' ), 10, 1 );

		$this->action_output .= $value . 2;

		return 'x2';
	}

	public function _filter_do_action_doesnt_change_value3( $value ) {
		$this->action_output .= $value . 3;

		return 'x3';
	}

	/**
	 * Use this rather than MockAction so we can test callbacks with no args
	 *
	 * @param mixed ...$args Optional arguments passed to the action.
	 */
	public function _action_callback( ...$args ) {
		$this->events[] = array(
			'action' => __FUNCTION__,
			'args'   => $args,
		);
	}

	/**
	 * @ticket 51894
	 *
	 * @dataProvider data_not_valid_callback
	 *
	 * @param mixed  $callback           Invalid callback to test.
	 * @param string $callback_as_string Callback as a string for the error message.
	 */
	public function test_not_valid_callback( $callback, $callback_as_string ) {
		remove_action( 'doing_it_wrong_trigger_error', '__return_false' );
		$this->setExpectedIncorrectUsage( 'WP_Hook::do_action' );
		$this->expectException( 'PHPUnit_Framework_Error_Notice' );
		$this->expectExceptionMessage(
			sprintf(
				'WP_Hook::do_action was called <strong>incorrectly</strong>. Requires <code>%s</code> to be a valid callback. Please see <a href="https://wordpress.org/support/article/debugging-in-wordpress/">Debugging in WordPress</a> for more information. (This message was added in version 5.6.0.)',
				$callback_as_string
			)
		);

		$hook = $this->setup_hook( __FUNCTION__, $callback );
		$arg  = __FUNCTION__ . '_arg';

		$hook->do_action( array( $arg ) );
	}
}
