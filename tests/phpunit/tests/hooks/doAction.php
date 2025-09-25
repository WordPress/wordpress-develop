<?php

/**
 * Test the do_action method of WP_Hook
 *
 * @group hooks
 * @covers WP_Hook::do_action
 */
class Tests_Hooks_DoAction extends WP_UnitTestCase {
	private $events        = array();
	private $action_output = '';
	private $hook;

	public function set_up() {
		parent::set_up();
		$this->events = array();
	}

	public function test_do_action_with_callback() {
		$a             = new MockAction();
		$callback      = array( $a, 'action' );
		$hook          = new WP_Hook();
		$hook_name     = __FUNCTION__;
		$priority      = 1;
		$accepted_args = 2;
		$arg           = __FUNCTION__ . '_arg';

		$hook->add_filter( $hook_name, $callback, $priority, $accepted_args );
		$hook->do_action( array( $arg ) );

		$this->assertSame( 1, $a->get_call_count() );
	}

	public function test_do_action_with_multiple_calls() {
		$a             = new MockAction();
		$callback      = array( $a, 'filter' );
		$hook          = new WP_Hook();
		$hook_name     = __FUNCTION__;
		$priority      = 1;
		$accepted_args = 2;
		$arg           = __FUNCTION__ . '_arg';

		$hook->add_filter( $hook_name, $callback, $priority, $accepted_args );
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
		$hook_name     = __FUNCTION__;
		$priority      = 1;
		$accepted_args = 2;
		$arg           = __FUNCTION__ . '_arg';

		$hook->add_filter( $hook_name, $callback_one, $priority, $accepted_args );
		$hook->add_filter( $hook_name, $callback_two, $priority, $accepted_args );
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
		$hook_name     = __FUNCTION__;
		$priority      = 1;
		$accepted_args = 2;
		$arg           = __FUNCTION__ . '_arg';

		$hook->add_filter( $hook_name, $callback_one, $priority, $accepted_args );
		$hook->add_filter( $hook_name, $callback_two, $priority + 1, $accepted_args );
		$hook->do_action( array( $arg ) );

		$this->assertSame( 1, $a->get_call_count() );
		$this->assertSame( 1, $a->get_call_count() );
	}

	/**
	 * @ticket 60193
	 *
	 * @dataProvider data_priority_callback_order_with_integers
	 * @dataProvider data_priority_callback_order_with_unhappy_path_nonintegers
	 *
	 * @param array $priorities {
	 *     Indexed array of the priorities for the MockAction callbacks.
	 *
	 *     @type mixed $0 Priority for 'action' callback.
	 *     @type mixed $1 Priority for 'action2' callback.
	 * }
	 * @param array  $expected_call_order  An array of callback names in expected call order.
	 * @param string $expected_deprecation Optional. Deprecation message. Default ''.
	 */
	public function test_priority_callback_order( $priorities, $expected_call_order, $expected_deprecation = '' ) {
		$mock      = new MockAction();
		$hook      = new WP_Hook();
		$hook_name = __FUNCTION__;

		if ( $expected_deprecation && PHP_VERSION_ID >= 80100 ) {
			$this->expectDeprecation();
			$this->expectDeprecationMessage( $expected_deprecation );
		}

		$hook->add_filter( $hook_name, array( $mock, 'action' ), $priorities[0], 1 );
		$hook->add_filter( $hook_name, array( $mock, 'action2' ), $priorities[1], 1 );
		$hook->do_action( array( '' ) );

		$this->assertSame( 2, $mock->get_call_count(), 'The number of call counts does not match' );

		$actual_call_order = wp_list_pluck( $mock->get_events(), 'action' );
		$this->assertSame( $expected_call_order, $actual_call_order, 'The action callback order does not match the expected order' );
	}

	/**
	 * Happy path data provider.
	 *
	 * @return array[]
	 */
	public function data_priority_callback_order_with_integers() {
		return array(
			'int DESC' => array(
				'priorities'          => array( 10, 9 ),
				'expected_call_order' => array( 'action2', 'action' ),
			),
			'int ASC'  => array(
				'priorities'          => array( 9, 10 ),
				'expected_call_order' => array( 'action', 'action2' ),
			),
		);
	}

	/**
	 * Unhappy path data provider.
	 *
	 * @return array[]
	 */
	public function data_priority_callback_order_with_unhappy_path_nonintegers() {
		return array(
			// Numbers as strings and floats.
			'int as string DESC'               => array(
				'priorities'          => array( '10', '9' ),
				'expected_call_order' => array( 'action2', 'action' ),
			),
			'int as string ASC'                => array(
				'priorities'          => array( '9', '10' ),
				'expected_call_order' => array( 'action', 'action2' ),
			),
			'float DESC'                       => array(
				'priorities'           => array( 10.0, 9.5 ),
				'expected_call_order'  => array( 'action2', 'action' ),
				'expected_deprecation' => 'Implicit conversion from float 9.5 to int loses precision',
			),
			'float ASC'                        => array(
				'priorities'           => array( 9.5, 10.0 ),
				'expected_call_order'  => array( 'action', 'action2' ),
				'expected_deprecation' => 'Implicit conversion from float 9.5 to int loses precision',
			),
			'float as string DESC'             => array(
				'priorities'          => array( '10.0', '9.5' ),
				'expected_call_order' => array( 'action2', 'action' ),
			),
			'float as string ASC'              => array(
				'priorities'          => array( '9.5', '10.0' ),
				'expected_call_order' => array( 'action', 'action2' ),
			),

			// Non-numeric.
			'null'                             => array(
				'priorities'          => array( null, null ),
				'expected_call_order' => array( 'action', 'action2' ),
			),
			'bool DESC'                        => array(
				'priorities'          => array( true, false ),
				'expected_call_order' => array( 'action2', 'action' ),
			),
			'bool ASC'                         => array(
				'priorities'          => array( false, true ),
				'expected_call_order' => array( 'action', 'action2' ),
			),
			'non-numerical string DESC'        => array(
				'priorities'          => array( 'test1', 'test2' ),
				'expected_call_order' => array( 'action', 'action2' ),
			),
			'non-numerical string ASC'         => array(
				'priorities'          => array( 'test1', 'test2' ),
				'expected_call_order' => array( 'action', 'action2' ),
			),
			'int, non-numerical string DESC'   => array(
				'priorities'          => array( 10, 'test' ),
				'expected_call_order' => array( 'action2', 'action' ),
			),
			'int, non-numerical string ASC'    => array(
				'priorities'          => array( 'test', 10 ),
				'expected_call_order' => array( 'action', 'action2' ),
			),
			'float, non-numerical string DESC' => array(
				'priorities'          => array( 10.0, 'test' ),
				'expected_call_order' => array( 'action2', 'action' ),
			),
			'float, non-numerical string ASC'  => array(
				'priorities'          => array( 'test', 10.0 ),
				'expected_call_order' => array( 'action', 'action2' ),
			),
		);
	}

	public function test_do_action_with_no_accepted_args() {
		$callback      = array( $this, '_action_callback' );
		$hook          = new WP_Hook();
		$hook_name     = __FUNCTION__;
		$priority      = 1;
		$accepted_args = 0;
		$arg           = __FUNCTION__ . '_arg';

		$hook->add_filter( $hook_name, $callback, $priority, $accepted_args );
		$hook->do_action( array( $arg ) );

		$this->assertEmpty( $this->events[0]['args'] );
	}

	public function test_do_action_with_one_accepted_arg() {
		$callback      = array( $this, '_action_callback' );
		$hook          = new WP_Hook();
		$hook_name     = __FUNCTION__;
		$priority      = 1;
		$accepted_args = 1;
		$arg           = __FUNCTION__ . '_arg';

		$hook->add_filter( $hook_name, $callback, $priority, $accepted_args );
		$hook->do_action( array( $arg ) );

		$this->assertCount( 1, $this->events[0]['args'] );
	}

	public function test_do_action_with_more_accepted_args() {
		$callback      = array( $this, '_action_callback' );
		$hook          = new WP_Hook();
		$hook_name     = __FUNCTION__;
		$priority      = 100;
		$accepted_args = 1000;
		$arg           = __FUNCTION__ . '_arg';

		$hook->add_filter( $hook_name, $callback, $priority, $accepted_args );
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
}
