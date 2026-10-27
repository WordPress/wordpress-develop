<?php
/**
 * Tests for register_theme_feature().
 *
 * @group themes
 *
 * @covers ::register_theme_feature
 */
class Tests_Theme_RegisterThemeFeature extends WP_UnitTestCase {
	/**
	 * @ticket 49406
	 *
	 * @dataProvider data_register_theme_support_validation
	 *
	 * @param string $error_code The error code expected.
	 * @param array  $args       The args to register.
	 */
	public function test_register_theme_support_validation( $error_code, $args ) {
		$registered = register_theme_feature( 'test-feature', $args );

		$this->assertWPError( $registered );
		$this->assertSame( $error_code, $registered->get_error_code() );
	}

	public function data_register_theme_support_validation() {
		return array(
			array(
				'invalid_type',
				array(
					'type' => 'float',
				),
			),
			array(
				'invalid_type',
				array(
					'type' => array( 'string' ),
				),
			),
			array(
				'variadic_must_be_array',
				array(
					'variadic' => true,
				),
			),
			array(
				'missing_schema',
				array(
					'type'         => 'object',
					'show_in_rest' => true,
				),
			),
			array(
				'missing_schema',
				array(
					'type'         => 'array',
					'show_in_rest' => true,
				),
			),
			array(
				'missing_schema_items',
				array(
					'type'         => 'array',
					'show_in_rest' => array(
						'schema' => array(
							'type' => 'array',
						),
					),
				),
			),
			array(
				'missing_schema_properties',
				array(
					'type'         => 'object',
					'show_in_rest' => array(
						'schema' => array(
							'type' => 'object',
						),
					),
				),
			),
			array(
				'invalid_rest_prepare_callback',
				array(
					'show_in_rest' => array(
						'prepare_callback' => 'this is not a valid function',
					),
				),
			),
		);
	}
}
