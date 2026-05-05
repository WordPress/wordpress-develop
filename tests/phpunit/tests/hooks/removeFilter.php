<?php

/**
 * Test the remove_filter method of WP_Hook
 *
 * @group hooks
 * @covers WP_Hook::remove_filter
 */
class Tests_Hooks_RemoveFilter extends WP_UnitTestCase {

	public function test_remove_filter_with_function() {
		$callback      = '__return_null';
		$hook          = new WP_Hook();
		$hook_name     = __FUNCTION__;
		$priority      = 1;
		$accepted_args = 2;

		$hook->add_filter( $hook_name, $callback, $priority, $accepted_args );
		$hook->remove_filter( $hook_name, $callback, $priority );
		$this->check_priority_non_existent( $hook, $priority );

		$this->assertArrayNotHasKey( $priority, $hook->callbacks );
	}

	public function test_remove_filter_with_object() {
		$a             = new MockAction();
		$callback      = array( $a, 'action' );
		$hook          = new WP_Hook();
		$hook_name     = __FUNCTION__;
		$priority      = 1;
		$accepted_args = 2;

		$hook->add_filter( $hook_name, $callback, $priority, $accepted_args );
		$hook->remove_filter( $hook_name, $callback, $priority );
		$this->check_priority_non_existent( $hook, $priority );

		$this->assertArrayNotHasKey( $priority, $hook->callbacks );
	}

	public function test_remove_filter_with_static_method() {
		$callback      = array( 'MockAction', 'action' );
		$hook          = new WP_Hook();
		$hook_name     = __FUNCTION__;
		$priority      = 1;
		$accepted_args = 2;

		$hook->add_filter( $hook_name, $callback, $priority, $accepted_args );
		$hook->remove_filter( $hook_name, $callback, $priority );
		$this->check_priority_non_existent( $hook, $priority );

		$this->assertArrayNotHasKey( $priority, $hook->callbacks );
	}

	public function test_remove_filters_with_another_at_same_priority() {
		$callback_one  = '__return_null';
		$callback_two  = '__return_false';
		$hook          = new WP_Hook();
		$hook_name     = __FUNCTION__;
		$priority      = 1;
		$accepted_args = 2;

		$hook->add_filter( $hook_name, $callback_one, $priority, $accepted_args );
		$hook->add_filter( $hook_name, $callback_two, $priority, $accepted_args );

		$hook->remove_filter( $hook_name, $callback_one, $priority );

		$this->assertCount( 1, $hook->callbacks[ $priority ] );
		$this->check_priority_exists( $hook, $priority, 'Has priority of 2' );
	}

	public function test_remove_filter_with_another_at_different_priority() {
		$callback_one  = '__return_null';
		$callback_two  = '__return_false';
		$hook          = new WP_Hook();
		$hook_name     = __FUNCTION__;
		$priority      = 1;
		$accepted_args = 2;

		$hook->add_filter( $hook_name, $callback_one, $priority, $accepted_args );
		$hook->add_filter( $hook_name, $callback_two, $priority + 1, $accepted_args );

		$hook->remove_filter( $hook_name, $callback_one, $priority );
		$this->check_priority_non_existent( $hook, $priority );
		$this->assertArrayNotHasKey( $priority, $hook->callbacks );
		$this->assertCount( 1, $hook->callbacks[ $priority + 1 ] );
		$this->check_priority_exists( $hook, $priority + 1, 'Should priority of 3' );
	}

	/**
	 * Removing the last callback at the currently iterating priority must not
	 * cause the next remaining priority to be silently skipped.
	 *
	 * @ticket 65167
	 *
	 * @covers WP_Hook::remove_filter
	 * @covers WP_Hook::apply_filters
	 */
	public function test_remove_filter_during_iteration_does_not_skip_next_priority() {
		$hook      = new WP_Hook();
		$hook_name = __FUNCTION__;
		$fired     = array();

		$early = static function ( $value ) use ( &$fired ) {
			$fired[] = 'early';
			return $value;
		};

		$self_removing = static function ( $value ) use ( &$hook, $hook_name, &$self_removing, &$fired ) {
			$fired[] = 'self_removing';
			$hook->remove_filter( $hook_name, $self_removing, 10 );
			return $value;
		};

		$later = static function ( $value ) use ( &$fired ) {
			$fired[] = 'later';
			return $value;
		};

		$hook->add_filter( $hook_name, $early, 5, 1 );
		$hook->add_filter( $hook_name, $self_removing, 10, 1 );
		$hook->add_filter( $hook_name, $later, 20, 1 );

		$hook->apply_filters( null, array( null ) );

		$this->assertSame( array( 'early', 'self_removing', 'later' ), $fired );
	}

	protected function check_priority_non_existent( $hook, $priority ) {
		$priorities = $this->get_priorities( $hook );

		$this->assertNotContains( $priority, $priorities );
	}

	protected function check_priority_exists( $hook, $priority ) {
		$priorities = $this->get_priorities( $hook );

		$this->assertContains( $priority, $priorities );
	}

	protected function get_priorities( $hook ) {
		$reflection          = new ReflectionClass( $hook );
		$reflection_property = $reflection->getProperty( 'priorities' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection_property->setAccessible( true );
		}

		return $reflection_property->getValue( $hook );
	}
}
