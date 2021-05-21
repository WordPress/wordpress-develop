<?php

/**
 * Test WP_Theme_JSON class.
 *
 * @package Gutenberg
 */

class WP_Theme_JSON_Test extends WP_UnitTestCase {

	function test_get_settings() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'color'       => array(
						'custom' => false,
					),
					'invalid/key' => 'value',
					'blocks'      => array(
						'core/group' => array(
							'color'       => array(
								'custom' => false,
							),
							'invalid/key' => 'value',
						),
					),
				),
				'styles'   => array(
					'color' => array(
						'link' => 'blue',
					),
				),
			)
		);

		$actual = $theme_json->get_settings();

		$expected = array(
			'color'  => array(
				'custom' => false,
			),
			'blocks' => array(
				'core/group' => array(
					'color' => array(
						'custom' => false,
					),
				),
			),
		);

		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	public function test_merge_incoming_data() {
		$initial = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(
				'color'  => array(
					'custom'  => false,
					'palette' => array(
						array(
							'slug'  => 'red',
							'color' => 'red',
						),
						array(
							'slug'  => 'green',
							'color' => 'green',
						),
					),
				),
				'blocks' => array(
					'core/paragraph' => array(
						'color' => array(
							'custom' => false,
						),
					),
				),
			),
			'styles'   => array(
				'typography' => array(
					'fontSize' => '12',
				),
			),
		);

		$add_new_block = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(
				'blocks' => array(
					'core/list' => array(
						'color' => array(
							'custom' => false,
						),
					),
				),
			),
			'styles'   => array(
				'blocks' => array(
					'core/list' => array(
						'typography' => array(
							'fontSize' => '12',
						),
						'color'      => array(
							'background' => 'brown',
						),
					),
				),
			),
		);

		$add_key_in_settings = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(
				'color' => array(
					'customGradient' => true,
				),
			),
		);

		$update_key_in_settings = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(
				'color' => array(
					'custom' => true,
				),
			),
		);

		$add_styles = array(
			'version' => WP_Theme_JSON::LATEST_SCHEMA,
			'styles'  => array(
				'blocks' => array(
					'core/group' => array(
						'spacing' => array(
							'padding' => array(
								'top' => '12px',
							),
						),
					),
				),
			),
		);

		$add_key_in_styles = array(
			'version' => WP_Theme_JSON::LATEST_SCHEMA,
			'styles'  => array(
				'blocks' => array(
					'core/group' => array(
						'spacing' => array(
							'padding' => array(
								'bottom' => '12px',
							),
						),
					),
				),
			),
		);

		$add_invalid_context = array(
			'version' => WP_Theme_JSON::LATEST_SCHEMA,
			'styles'  => array(
				'blocks' => array(
					'core/para' => array(
						'typography' => array(
							'lineHeight' => '12',
						),
					),
				),
			),
		);

		$update_presets = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(
				'color'      => array(
					'palette'   => array(
						array(
							'slug'  => 'blue',
							'color' => 'blue',
						),
					),
					'gradients' => array(
						array(
							'slug'     => 'gradient',
							'gradient' => 'gradient',
						),
					),
				),
				'typography' => array(
					'fontSizes'    => array(
						array(
							'slug' => 'fontSize',
							'size' => 'fontSize',
						),
					),
					'fontFamilies' => array(
						array(
							'slug'       => 'fontFamily',
							'fontFamily' => 'fontFamily',
						),
					),
				),
			),
		);

		$expected = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(
				'color'      => array(
					'custom'         => true,
					'customGradient' => true,
					'palette'        => array(
						array(
							'slug'  => 'blue',
							'color' => 'blue',
						),
					),
					'gradients'      => array(
						array(
							'slug'     => 'gradient',
							'gradient' => 'gradient',
						),
					),
				),
				'typography' => array(
					'fontSizes' => array(
						array(
							'slug' => 'fontSize',
							'size' => 'fontSize',
						),
					),
				),
				'blocks'     => array(
					'core/paragraph' => array(
						'color' => array(
							'custom' => false,
						),
					),
					'core/list'      => array(
						'color' => array(
							'custom' => false,
						),
					),
				),
			),
		);

		$theme_json = new WP_Theme_JSON( $initial );
		$theme_json->merge( new WP_Theme_JSON( $add_new_block ) );
		$theme_json->merge( new WP_Theme_JSON( $add_key_in_settings ) );
		$theme_json->merge( new WP_Theme_JSON( $update_key_in_settings ) );
		$theme_json->merge( new WP_Theme_JSON( $add_styles ) );
		$theme_json->merge( new WP_Theme_JSON( $add_key_in_styles ) );
		$theme_json->merge( new WP_Theme_JSON( $add_invalid_context ) );
		$theme_json->merge( new WP_Theme_JSON( $update_presets ) );
		$actual = $theme_json->get_raw_data();

		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	function test_get_from_editor_settings() {
		$input = array(
			'disableCustomColors'    => true,
			'disableCustomGradients' => true,
			'disableCustomFontSizes' => true,
			'enableCustomLineHeight' => true,
			'enableCustomUnits'      => true,
			'colors'                 => array(
				array(
					'slug'  => 'color-slug',
					'name'  => 'Color Name',
					'color' => 'colorvalue',
				),
			),
			'gradients'              => array(
				array(
					'slug'     => 'gradient-slug',
					'name'     => 'Gradient Name',
					'gradient' => 'gradientvalue',
				),
			),
			'fontSizes'              => array(
				array(
					'slug' => 'size-slug',
					'name' => 'Size Name',
					'size' => 'sizevalue',
				),
			),
		);

		$expected = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(
				'color'      => array(
					'custom'         => false,
					'customGradient' => false,
					'gradients'      => array(
						array(
							'slug'     => 'gradient-slug',
							'name'     => 'Gradient Name',
							'gradient' => 'gradientvalue',
						),
					),
					'palette'        => array(
						array(
							'slug'  => 'color-slug',
							'name'  => 'Color Name',
							'color' => 'colorvalue',
						),
					),
				),
				'spacing'    => array(
					'units' => array( 'px', 'em', 'rem', 'vh', 'vw' ),
				),
				'typography' => array(
					'customFontSize'   => false,
					'customLineHeight' => true,
					'fontSizes'        => array(
						array(
							'slug' => 'size-slug',
							'name' => 'Size Name',
							'size' => 'sizevalue',
						),
					),
				),
			),
		);

		$actual = WP_Theme_JSON::get_from_editor_settings( $input );

		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	function test_get_editor_settings_no_theme_support() {
		$input = array(
			'__unstableEnableFullSiteEditingBlocks' => false,
			'disableCustomColors'                   => false,
			'disableCustomFontSizes'                => false,
			'disableCustomGradients'                => false,
			'enableCustomLineHeight'                => false,
			'enableCustomUnits'                     => false,
			'imageSizes'                            => array(
				array(
					'slug' => 'thumbnail',
					'name' => 'Thumbnail',
				),
				array(
					'slug' => 'medium',
					'name' => 'Medium',
				),
				array(
					'slug' => 'large',
					'name' => 'Large',
				),
				array(
					'slug' => 'full',
					'name' => 'Full Size',
				),
			),
			'isRTL'                                 => false,
			'maxUploadFileSize'                     => 123,
		);

		$expected = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(
				'color'      => array(
					'custom'         => true,
					'customGradient' => true,
				),
				'spacing'    => array(
					'units' => false,
				),
				'typography' => array(
					'customFontSize'   => true,
					'customLineHeight' => false,
				),
			),
		);

		$actual = WP_Theme_JSON::get_from_editor_settings( $input );

		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	function test_get_editor_settings_blank() {
		$expected = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(),
		);
		$actual   = WP_Theme_JSON::get_from_editor_settings( array() );

		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	function test_get_editor_settings_custom_units_can_be_disabled() {
		add_theme_support( 'custom-units', array() );
		$input = get_default_block_editor_settings();

		$expected = array(
			'units'         => array( array() ),
			'customPadding' => false,
		);

		$actual = WP_Theme_JSON::get_from_editor_settings( $input );

		$this->assertEqualSetsWithIndex( $expected, $actual['settings']['spacing'] );
	}

	function test_get_editor_settings_custom_units_can_be_enabled() {
		add_theme_support( 'custom-units' );
		$input = get_default_block_editor_settings();

		$expected = array(
			'units'         => array( 'px', 'em', 'rem', 'vh', 'vw' ),
			'customPadding' => false,
		);

		$actual = WP_Theme_JSON::get_from_editor_settings( $input );

		$this->assertEqualSetsWithIndex( $expected, $actual['settings']['spacing'] );
	}

	function test_get_editor_settings_custom_units_can_be_filtered() {
		add_theme_support( 'custom-units', 'rem', 'em' );
		$input = get_default_block_editor_settings();

		$expected = array(
			'units'         => array( 'rem', 'em' ),
			'customPadding' => false,
		);

		$actual = WP_Theme_JSON::get_from_editor_settings( $input );

		$this->assertEqualSetsWithIndex( $expected, $actual['settings']['spacing'] );
	}

}
