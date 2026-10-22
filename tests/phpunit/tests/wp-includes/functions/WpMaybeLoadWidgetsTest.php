<?php

namespace WordPress\Tests\WP_Includes\Functions;

use WP_UnitTestCase;

/**
 * Tests for the `wp_maybe_load_widgets()` function.
 *
 * @group functions
 *
 * @covers ::wp_maybe_load_widgets
 */
class WpMaybeLoadWidgetsTest extends WP_UnitTestCase {

	/**
	 * Tests that wp_maybe_load_widgets() hooks wp_widgets_add_menu() to _admin_menu.
	 *
	 * @ticket 57201
	 */
	public function test_should_hook_widgets_menu() {
		// The widgets library is loaded during bootstrap, so reset the hook first.
		remove_action( '_admin_menu', 'wp_widgets_add_menu' );

		wp_maybe_load_widgets();

		$this->assertSame( 10, has_action( '_admin_menu', 'wp_widgets_add_menu' ) );
	}

	/**
	 * Tests that wp_maybe_load_widgets() does not hook wp_widgets_add_menu() when load_default_widgets filter is falsy.
	 *
	 * @ticket 57201
	 *
	 * @dataProvider data_falsy_values
	 *
	 * @param mixed $value Falsy value.
	 */
	public function test_should_not_hook_widgets_menu_when_filter_is_falsy( $value ) {
		remove_action( '_admin_menu', 'wp_widgets_add_menu' );
		add_filter(
			'load_default_widgets',
			static function () use ( $value ) {
				return $value;
			}
		);

		wp_maybe_load_widgets();

		$this->assertFalse( has_action( '_admin_menu', 'wp_widgets_add_menu' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_falsy_values() {
		return array(
			'false'        => array( false ),
			'0'            => array( 0 ),
			'empty string' => array( '' ),
			'null'         => array( null ),
		);
	}
}
