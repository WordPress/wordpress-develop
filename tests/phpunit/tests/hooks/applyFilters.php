<?php

use phpunit\tests\hooks\HooksTrait;

/**
 * Test the apply_filters method of WP_Hook
 *
 * @group hooks
 *
 * @covers WP_Hook::apply_filters
 */
class Tests_WP_Hook_Apply_Filters extends WP_UnitTestCase {
	use HooksTrait;

	public function test_apply_filters_with_callback() {
		$a             = new MockAction();
		$callback      = array( $a, 'filter' );
		$hook          = new WP_Hook();
		$tag           = __FUNCTION__;
		$priority      = rand( 1, 100 );
		$accepted_args = rand( 1, 100 );
		$arg           = __FUNCTION__ . '_arg';

		$hook->add_filter( $tag, $callback, $priority, $accepted_args );

		$returned = $hook->apply_filters( $arg, array( $arg ) );

		$this->assertSame( $returned, $arg );
		$this->assertSame( 1, $a->get_call_count() );
	}

	public function test_apply_filters_with_multiple_calls() {
		$a             = new MockAction();
		$callback      = array( $a, 'filter' );
		$hook          = new WP_Hook();
		$tag           = __FUNCTION__;
		$priority      = rand( 1, 100 );
		$accepted_args = rand( 1, 100 );
		$arg           = __FUNCTION__ . '_arg';

		$hook->add_filter( $tag, $callback, $priority, $accepted_args );

		$returned_one = $hook->apply_filters( $arg, array( $arg ) );
		$returned_two = $hook->apply_filters( $returned_one, array( $returned_one ) );

		$this->assertSame( $returned_two, $arg );
		$this->assertSame( 2, $a->get_call_count() );
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
		$this->setExpectedIncorrectUsage( 'WP_Hook::apply_filters' );
		$this->expectException( 'PHPUnit_Framework_Error_Notice' );
		$this->expectExceptionMessage(
			sprintf(
				'WP_Hook::apply_filters was called <strong>incorrectly</strong>. Requires <code>%s</code> to be a valid callback. Please see <a href="https://wordpress.org/support/article/debugging-in-wordpress/">Debugging in WordPress</a> for more information. (This message was added in version 5.6.0.)',
				$callback_as_string
			)
		);

		$hook = $this->setup_hook( __FUNCTION__, $callback );
		$arg  = __FUNCTION__ . '_arg';

		$actual = $hook->apply_filters( $arg, array( $arg ) );
		$this->assertSame( $arg, $actual );
	}
}
