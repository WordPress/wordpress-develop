<?php

/**
 * Test WP_Theme_JSON_Schema class.
 *
 * @package WordPress
 * @subpackage Theme
 *
 * @since 5.9.0
 *
 * @group themes
 */
class Tests_Theme_wpThemeJsonSchema extends WP_UnitTestCase {
	/**
	 * The current theme.json schema version.
	 */
	const LATEST_SCHEMA_VERSION = WP_Theme_JSON::LATEST_SCHEMA;

	/**
	 * @ticket 54336
	 */
	public function test_migrate_v1_to_v2() {
		$theme_json_v1 = array(
			'version'  => 1,
			'settings' => array(
				'color'      => array(
					'palette' => array(
						array(
							'name'  => 'Pale Pink',
							'slug'  => 'pale-pink',
							'color' => '#f78da7',
						),
						array(
							'name'  => 'Vivid Red',
							'slug'  => 'vivid-red',
							'color' => '#cf2e2e',
						),
					),
					'custom'  => false,
					'link'    => true,
				),
				'border'     => array(
					'color'        => false,
					'customRadius' => false,
					'style'        => false,
					'width'        => false,
				),
				'typography' => array(
					'fontStyle'      => false,
					'fontWeight'     => false,
					'letterSpacing'  => false,
					'textDecoration' => false,
					'textTransform'  => false,
				),
				'blocks'     => array(
					'core/group' => array(
						'border'     => array(
							'color'        => true,
							'customRadius' => true,
							'style'        => true,
							'width'        => true,
						),
						'typography' => array(
							'fontStyle'      => true,
							'fontWeight'     => true,
							'letterSpacing'  => true,
							'textDecoration' => true,
							'textTransform'  => true,
						),
					),
				),
			),
			'styles'   => array(
				'color'    => array(
					'background' => 'purple',
				),
				'blocks'   => array(
					'core/group' => array(
						'color'    => array(
							'background' => 'red',
						),
						'spacing'  => array(
							'padding' => array(
								'top' => '10px',
							),
						),
						'elements' => array(
							'link' => array(
								'color' => array(
									'text' => 'yellow',
								),
							),
						),
					),
				),
				'elements' => array(
					'link' => array(
						'color' => array(
							'text' => 'red',
						),
					),
				),
			),
		);

		$actual = WP_Theme_JSON_Schema::migrate( $theme_json_v1 );

		$expected = array(
			'version'  => self::LATEST_SCHEMA_VERSION,
			'settings' => array(
				'color'      => array(
					'palette' => array(
						array(
							'name'  => 'Pale Pink',
							'slug'  => 'pale-pink',
							'color' => '#f78da7',
						),
						array(
							'name'  => 'Vivid Red',
							'slug'  => 'vivid-red',
							'color' => '#cf2e2e',
						),
					),
					'custom'  => false,
					'link'    => true,
				),
				'border'     => array(
					'color'  => false,
					'radius' => false,
					'style'  => false,
					'width'  => false,
				),
				'typography' => array(
					'fontStyle'      => false,
					'fontWeight'     => false,
					'letterSpacing'  => false,
					'textDecoration' => false,
					'textTransform'  => false,
				),
				'blocks'     => array(
					'core/group' => array(
						'border'     => array(
							'color'  => true,
							'radius' => true,
							'style'  => true,
							'width'  => true,
						),
						'typography' => array(
							'fontStyle'      => true,
							'fontWeight'     => true,
							'letterSpacing'  => true,
							'textDecoration' => true,
							'textTransform'  => true,
						),
					),
				),
			),
			'styles'   => array(
				'color'    => array(
					'background' => 'purple',
				),
				'blocks'   => array(
					'core/group' => array(
						'color'    => array(
							'background' => 'red',
						),
						'spacing'  => array(
							'padding' => array(
								'top' => '10px',
							),
						),
						'elements' => array(
							'link' => array(
								'color' => array(
									'text' => 'yellow',
								),
							),
						),
					),
				),
				'elements' => array(
					'link' => array(
						'color' => array(
							'text' => 'red',
						),
					),
				),
			),
		);

		$this->assertEqualSetsWithIndex( $expected, $actual );
	}
}
