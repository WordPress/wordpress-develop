<?php

/**
 * @group admin
 *
 * @covers ::wp_admin_viewport_meta
 */
class Tests_Admin_Includes_Misc_WpAdminViewportMeta_Test extends WP_UnitTestCase {

	/**
	 * Tests wp_admin_viewport_meta() output.
	 *
	 * @dataProvider data_wp_admin_viewport_meta
	 *
	 * @ticket 65187
	 *
	 * @param string|null $filter_value The value to return from the filter, or null if no filter.
	 * @param string      $expected     The expected output string.
	 */
	public function test_wp_admin_viewport_meta( $filter_value, $expected ) {
		if ( null !== $filter_value ) {
			add_filter(
				'admin_viewport_meta',
				function () use ( $filter_value ) {
					return $filter_value;
				}
			);
		}

		$this->expectOutputString( $expected );
		wp_admin_viewport_meta();
	}

	/**
	 * Data provider for test_wp_admin_viewport_meta().
	 *
	 * @return array<string, array{
	 *     filter_value: string|null,
	 *     expected:     string,
	 * }>
	 */
	public function data_wp_admin_viewport_meta(): array {
		return array(
			'default value'          => array(
				'filter_value' => null,
				'expected'     => '<meta name="viewport" content="width=device-width,initial-scale=1.0">',
			),
			'custom filtered value'  => array(
				'filter_value' => 'width=device-width,initial-scale=2.0',
				'expected'     => '<meta name="viewport" content="width=device-width,initial-scale=2.0">',
			),
			'empty filtered value'   => array(
				'filter_value' => '',
				'expected'     => '',
			),
			'escaped filtered value' => array(
				'filter_value' => 'width=device-width; content="><script>alert(1)</script>',
				'expected'     => '<meta name="viewport" content="' . esc_attr( 'width=device-width; content="><script>alert(1)</script>' ) . '">',
			),
		);
	}
}
