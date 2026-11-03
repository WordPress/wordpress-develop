<?php
/**
 * Unit tests covering the wp_is_registered_icon_name() function.
 *
 * @package WordPress
 * @subpackage Icons
 * @since 7.2.0
 *
 * @group icons
 *
 * @covers ::wp_is_registered_icon_name
 */
class Tests_Icons_WpIsRegisteredIconName extends WP_UnitTestCase {

	/**
	 * @ticket 65089
	 */
	public function test_returns_true_for_registered_icon(): void {
		$this->assertTrue( wp_is_registered_icon_name( 'core-admin/pin' ) );
	}

	/**
	 * @ticket 65089
	 *
	 * @dataProvider data_returns_false_for_other_values
	 *
	 * @param mixed $value The value to check.
	 */
	public function test_returns_false_for_other_values( $value ): void {
		$this->assertFalse( wp_is_registered_icon_name( $value ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ 0: mixed }>
	 */
	public static function data_returns_false_for_other_values(): array {
		return array(
			'name that is not registered' => array( 'core-admin/not-registered' ),
			'Dashicons class'             => array( 'dashicons-admin-post' ),
			'empty string'                => array( '' ),
			'false'                       => array( false ),
			'null'                        => array( null ),
			'array'                       => array( array( 'core-admin/pin' ) ),
		);
	}
}
