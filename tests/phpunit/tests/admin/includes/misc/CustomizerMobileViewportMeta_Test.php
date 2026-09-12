<?php

/**
 * @group admin
 *
 * @covers ::_customizer_mobile_viewport_meta
 */
class Tests_Admin_Includes_Misc_CustomizerMobileViewportMeta_Test extends WP_UnitTestCase {

	/**
	 * Tests _customizer_mobile_viewport_meta().
	 *
	 * @dataProvider data_customizer_mobile_viewport_meta
	 *
	 * @ticket 65186
	 *
	 * @param string $viewport_meta Original viewport meta.
	 * @param string $expected      Expected viewport meta.
	 */
	public function test_customizer_mobile_viewport_meta( $viewport_meta, $expected ) {
		$this->assertSame( $expected, _customizer_mobile_viewport_meta( $viewport_meta ) );
	}

	/**
	 * Data provider for test_customizer_mobile_viewport_meta().
	 *
	 * @return array<string, array{
	 *     viewport_meta: string,
	 *     expected:      string,
	 * }>
	 */
	public function data_customizer_mobile_viewport_meta(): array {
		return array(
			'default'                       => array(
				'viewport_meta' => 'width=device-width,initial-scale=1.0',
				'expected'      => 'width=device-width,initial-scale=1.0,minimum-scale=0.5,maximum-scale=1.2',
			),
			'empty'                         => array(
				'viewport_meta' => '',
				'expected'      => ',minimum-scale=0.5,maximum-scale=1.2',
			),
			'with trailing comma'           => array(
				'viewport_meta' => 'width=device-width,initial-scale=1.0,',
				'expected'      => 'width=device-width,initial-scale=1.0,minimum-scale=0.5,maximum-scale=1.2',
			),
			'with multiple trailing commas' => array(
				'viewport_meta' => 'width=device-width,initial-scale=1.0,,',
				'expected'      => 'width=device-width,initial-scale=1.0,minimum-scale=0.5,maximum-scale=1.2',
			),
		);
	}
}
