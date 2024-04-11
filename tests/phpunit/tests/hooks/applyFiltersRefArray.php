<?php

/**
 * Test apply_filters_ref_array().
 *
 * @group hooks
 * @covers ::apply_filters_ref_array
 */
class Tests_Hooks_ApplyFiltersRefArray extends WP_UnitTestCase {

	/**
	 * @ticket 9886
	 */
	public function test_filter_ref_array() {
		$obj       = new stdClass();
		$a         = new MockAction();
		$hook_name = __FUNCTION__;

		add_action( $hook_name, array( $a, 'filter' ) );

		apply_filters_ref_array( $hook_name, array( &$obj ) );

		$args = $a->get_args();
		$this->assertSame( $args[0][0], $obj );
		// Just in case we don't trust assertSame().
		$obj->foo = true;
		$this->assertNotEmpty( $args[0][0]->foo );
	}

	/**
	 * @ticket 12723
	 */
	public function test_filter_ref_array_result() {
		$obj       = new stdClass();
		$a         = new MockAction();
		$b         = new MockAction();
		$hook_name = __FUNCTION__;

		add_action( $hook_name, array( $a, 'filter_append' ), 10, 2 );
		add_action( $hook_name, array( $b, 'filter_append' ), 10, 2 );

		$result = apply_filters_ref_array( $hook_name, array( 'string', &$obj ) );

		$this->assertSame( $result, 'string_append_append' );

		$args = $a->get_args();
		$this->assertSame( $args[0][1], $obj );
		// Just in case we don't trust assertSame().
		$obj->foo = true;
		$this->assertNotEmpty( $args[0][1]->foo );

		$args = $b->get_args();
		$this->assertSame( $args[0][1], $obj );
		// Just in case we don't trust assertSame().
		$obj->foo = true;
		$this->assertNotEmpty( $args[0][1]->foo );
	}
}
