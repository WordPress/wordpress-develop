<?php

/**
 * Test do_action_deprecated().
 *
 * @group hooks
 * @covers ::do_action_deprecated
 */
class Tests_Hooks_DoActionDeprecated extends WP_UnitTestCase {
	/**
	 * @ticket 10441
	 * @expectedDeprecated tests_do_action_deprecated
	 */
	public function test_do_action_deprecated() {
		$p = new WP_Post( (object) array( 'post_title' => 'Foo' ) );

		add_action( 'tests_do_action_deprecated', array( __CLASS__, 'deprecated_action_callback' ) );
		do_action_deprecated( 'tests_do_action_deprecated', array( $p ), '4.6.0' );
		remove_action( 'tests_do_action_deprecated', array( __CLASS__, 'deprecated_action_callback' ) );

		$this->assertSame( 'Bar', $p->post_title );
	}

	public static function deprecated_action_callback( $p ) {
		$p->post_title = 'Bar';
	}

	/**
	 * @ticket 10441
	 * @expectedDeprecated tests_do_action_deprecated
	 */
	public function test_do_action_deprecated_with_multiple_params() {
		$p1 = new WP_Post( (object) array( 'post_title' => 'Foo1' ) );
		$p2 = new WP_Post( (object) array( 'post_title' => 'Foo2' ) );

		add_action( 'tests_do_action_deprecated', array( __CLASS__, 'deprecated_action_callback_multiple_params' ), 10, 2 );
		do_action_deprecated( 'tests_do_action_deprecated', array( $p1, $p2 ), '4.6.0' );
		remove_action( 'tests_do_action_deprecated', array( __CLASS__, 'deprecated_action_callback_multiple_params' ), 10, 2 );

		$this->assertSame( 'Bar1', $p1->post_title );
		$this->assertSame( 'Bar2', $p2->post_title );
	}

	public static function deprecated_action_callback_multiple_params( $p1, $p2 ) {
		$p1->post_title = 'Bar1';
		$p2->post_title = 'Bar2';
	}
}
