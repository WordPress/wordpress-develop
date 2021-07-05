<?php

/**
 * Test WP_Theme_JSON class.
 *
 * @package WordPress
 * @subpackage Theme
 *
 * @since 5.8.0
 *
 * @group themes
 */

class Tests_Theme_wpThemeJson extends WP_UnitTestCase {

	/**
	 * @ticket 52991
	 */
	public function test_get_settings() {
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
					'elements' => array(
						'link' => array(
							'color' => array(
								'text' => '#111',
							),
						),
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

		$this->assertSameSetsWithIndex( $expected, $actual );
	}

	/**
	 * @ticket 53397
	 */
	public function test_get_settings_presets_are_keyed_by_origin() {
		$core_origin = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'color'       => array(
						'palette' => array(
							array(
								'slug'  => 'white',
								'color' => 'white',
							),
						),
					),
					'invalid/key' => 'value',
					'blocks'      => array(
						'core/group' => array(
							'color' => array(
								'palette' => array(
									array(
										'slug'  => 'white',
										'color' => 'white',
									),
								),
							),
						),
					),
				),
			),
			'core'
		);
		$no_origin   = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'color'       => array(
						'palette' => array(
							array(
								'slug'  => 'black',
								'color' => 'black',
							),
						),
					),
					'invalid/key' => 'value',
					'blocks'      => array(
						'core/group' => array(
							'color' => array(
								'palette' => array(
									array(
										'slug'  => 'black',
										'color' => 'black',
									),
								),
							),
						),
					),
				),
			)
		);

		$actual_core      = $core_origin->get_raw_data();
		$actual_no_origin = $no_origin->get_raw_data();

		$expected_core      = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(
				'color'  => array(
					'palette' => array(
						'core' => array(
							array(
								'slug'  => 'white',
								'color' => 'white',
							),
						),
					),
				),
				'blocks' => array(
					'core/group' => array(
						'color' => array(
							'palette' => array(
								'core' => array(
									array(
										'slug'  => 'white',
										'color' => 'white',
									),
								),
							),
						),
					),
				),
			),
		);
		$expected_no_origin = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(
				'color'  => array(
					'palette' => array(
						'theme' => array(
							array(
								'slug'  => 'black',
								'color' => 'black',
							),
						),
					),
				),
				'blocks' => array(
					'core/group' => array(
						'color' => array(
							'palette' => array(
								'theme' => array(
									array(
										'slug'  => 'black',
										'color' => 'black',
									),
								),
							),
						),
					),
				),
			),
		);

		$this->assertEqualSetsWithIndex( $expected_core, $actual_core );
		$this->assertEqualSetsWithIndex( $expected_no_origin, $actual_no_origin );
	}

	/**
	 * @ticket 53175
	 */
	public function test_get_stylesheet() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'color'      => array(
						'text'    => 'value',
						'palette' => array(
							array(
								'slug'  => 'grey',
								'color' => 'grey',
							),
						),
					),
					'typography' => array(
						'fontFamilies' => array(
							array(
								'slug'       => 'small',
								'fontFamily' => '14px',
							),
							array(
								'slug'       => 'big',
								'fontFamily' => '41px',
							),
						),
					),
					'misc'       => 'value',
					'blocks'     => array(
						'core/group' => array(
							'custom' => array(
								'base-font'   => 16,
								'line-height' => array(
									'small'  => 1.2,
									'medium' => 1.4,
									'large'  => 1.8,
								),
							),
						),
					),
				),
				'styles'   => array(
					'color'    => array(
						'text' => 'var:preset|color|grey',
					),
					'misc'     => 'value',
					'elements' => array(
						'link' => array(
							'color' => array(
								'text'       => '#111',
								'background' => '#333',
							),
						),
					),
					'blocks'   => array(
						'core/group'     => array(
							'elements' => array(
								'link' => array(
									'color' => array(
										'text' => '#111',
									),
								),
							),
							'spacing'  => array(
								'padding' => array(
									'top'    => '12px',
									'bottom' => '24px',
								),
							),
						),
						'core/heading'   => array(
							'color'    => array(
								'text' => '#123456',
							),
							'elements' => array(
								'link' => array(
									'color'      => array(
										'text'       => '#111',
										'background' => '#333',
									),
									'typography' => array(
										'fontSize' => '60px',
									),
								),
							),
						),
						'core/post-date' => array(
							'color'    => array(
								'text' => '#123456',
							),
							'elements' => array(
								'link' => array(
									'color' => array(
										'background' => '#777',
										'text'       => '#555',
									),
								),
							),
						),
					),
				),
				'misc'     => 'value',
			)
		);

		$this->assertSame(
			'body{--wp--preset--color--grey: grey;}.wp-block-group{--wp--custom--base-font: 16;--wp--custom--line-height--small: 1.2;--wp--custom--line-height--medium: 1.4;--wp--custom--line-height--large: 1.8;}body{color: var(--wp--preset--color--grey);}a{background-color: #333;color: #111;}.wp-block-group{padding-top: 12px;padding-bottom: 24px;}.wp-block-group a{color: #111;}h1,h2,h3,h4,h5,h6{color: #123456;}h1 a,h2 a,h3 a,h4 a,h5 a,h6 a{background-color: #333;color: #111;font-size: 60px;}.wp-block-post-date{color: #123456;}.wp-block-post-date a{background-color: #777;color: #555;}.has-grey-color{color: var(--wp--preset--color--grey) !important;}.has-grey-background-color{background-color: var(--wp--preset--color--grey) !important;}',
			$theme_json->get_stylesheet()
		);
		$this->assertSame(
			'body{color: var(--wp--preset--color--grey);}a{background-color: #333;color: #111;}.wp-block-group{padding-top: 12px;padding-bottom: 24px;}.wp-block-group a{color: #111;}h1,h2,h3,h4,h5,h6{color: #123456;}h1 a,h2 a,h3 a,h4 a,h5 a,h6 a{background-color: #333;color: #111;font-size: 60px;}.wp-block-post-date{color: #123456;}.wp-block-post-date a{background-color: #777;color: #555;}.has-grey-color{color: var(--wp--preset--color--grey) !important;}.has-grey-background-color{background-color: var(--wp--preset--color--grey) !important;}',
			$theme_json->get_stylesheet( 'block_styles' )
		);
		$this->assertSame(
			'body{--wp--preset--color--grey: grey;}.wp-block-group{--wp--custom--base-font: 16;--wp--custom--line-height--small: 1.2;--wp--custom--line-height--medium: 1.4;--wp--custom--line-height--large: 1.8;}',
			$theme_json->get_stylesheet( 'css_variables' )
		);
	}

	/**
	 * @ticket 52991
	 */
	public function test_get_stylesheet_preset_classes_work_with_compounded_selectors() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'blocks' => array(
						'core/heading' => array(
							'color' => array(
								'palette' => array(
									array(
										'slug'  => 'white',
										'color' => '#fff',
									),
								),
							),
						),
					),
				),
			)
		);

		$this->assertSame(
			'h1.has-white-color,h2.has-white-color,h3.has-white-color,h4.has-white-color,h5.has-white-color,h6.has-white-color{color: var(--wp--preset--color--white) !important;}h1.has-white-background-color,h2.has-white-background-color,h3.has-white-background-color,h4.has-white-background-color,h5.has-white-background-color,h6.has-white-background-color{background-color: var(--wp--preset--color--white) !important;}',
			$theme_json->get_stylesheet( 'block_styles' )
		);
	}

	/**
	 * @ticket 53175
	 */
	public function test_get_stylesheet_preset_rules_come_after_block_rules() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'blocks' => array(
						'core/group' => array(
							'color' => array(
								'palette' => array(
									array(
										'slug'  => 'grey',
										'color' => 'grey',
									),
								),
							),
						),
					),
				),
				'styles'   => array(
					'blocks' => array(
						'core/group' => array(
							'color' => array(
								'text' => 'red',
							),
						),
					),
				),
			)
		);

		$this->assertSame(
			'.wp-block-group{--wp--preset--color--grey: grey;}.wp-block-group{color: red;}.wp-block-group.has-grey-color{color: var(--wp--preset--color--grey) !important;}.wp-block-group.has-grey-background-color{background-color: var(--wp--preset--color--grey) !important;}',
			$theme_json->get_stylesheet()
		);
		$this->assertSame(
			'.wp-block-group{color: red;}.wp-block-group.has-grey-color{color: var(--wp--preset--color--grey) !important;}.wp-block-group.has-grey-background-color{background-color: var(--wp--preset--color--grey) !important;}',
			$theme_json->get_stylesheet( 'block_styles' )
		);
	}

	/**
	 * @ticket 53175
	 */
	public function test_get_stylesheet_preset_values_are_marked_as_important() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'color' => array(
						'palette' => array(
							array(
								'slug'  => 'grey',
								'color' => 'grey',
							),
						),
					),
				),
				'styles'   => array(
					'blocks' => array(
						'core/paragraph' => array(
							'color'      => array(
								'text'       => 'red',
								'background' => 'blue',
							),
							'typography' => array(
								'fontSize'   => '12px',
								'lineHeight' => '1.3',
							),
						),
					),
				),
			),
			'core'
		);

		$this->assertSame(
			'body{--wp--preset--color--grey: grey;}p{background-color: blue;color: red;font-size: 12px;line-height: 1.3;}.has-grey-color{color: var(--wp--preset--color--grey) !important;}.has-grey-background-color{background-color: var(--wp--preset--color--grey) !important;}',
			$theme_json->get_stylesheet()
		);
	}

	/**
	 * @ticket 52991
	 */
	public function test_merge_incoming_data() {
		$theme_json = new WP_Theme_JSON(
			array(
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
			)
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
						'theme' => array(
							array(
								'slug'  => 'blue',
								'color' => 'blue',
							),
						),
					),
					'gradients'      => array(
						'theme' => array(
							array(
								'slug'     => 'gradient',
								'gradient' => 'gradient',
							),
						),
					),
				),
				'typography' => array(
					'fontSizes' => array(
						'theme' => array(
							array(
								'slug' => 'fontSize',
								'size' => 'fontSize',
							),
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
			'styles'   => array(
				'typography' => array(
					'fontSize' => '12',
				),
				'blocks'     => array(
					'core/group' => array(
						'spacing' => array(
							'padding' => array(
								'top'    => '12px',
								'bottom' => '12px',
							),
						),
					),
					'core/list'  => array(
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

	/**
	 * @ticket 52991
	 */
	public function test_get_from_editor_settings() {
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

	/**
	 * @ticket 52991
	 */
	public function test_get_editor_settings_no_theme_support() {
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

	/**
	 * @ticket 52991
	 */
	public function test_get_editor_settings_blank() {
		$expected = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(),
		);
		$actual   = WP_Theme_JSON::get_from_editor_settings( array() );

		$this->assertSameSetsWithIndex( $expected, $actual );
	}

	/**
	 * @ticket 52991
	 */
	public function test_get_editor_settings_custom_units_can_be_disabled() {
		add_theme_support( 'custom-units', array() );
		$input = get_default_block_editor_settings();

		$expected = array(
			'units'         => array( array() ),
			'customPadding' => false,
		);

		$actual = WP_Theme_JSON::get_from_editor_settings( $input );

		$this->assertSameSetsWithIndex( $expected, $actual['settings']['spacing'] );
	}

	/**
	 * @ticket 52991
	 */
	public function test_get_editor_settings_custom_units_can_be_enabled() {
		add_theme_support( 'custom-units' );
		$input = get_default_block_editor_settings();

		$expected = array(
			'units'         => array( 'px', 'em', 'rem', 'vh', 'vw' ),
			'customPadding' => false,
		);

		$actual = WP_Theme_JSON::get_from_editor_settings( $input );

		$this->assertSameSetsWithIndex( $expected, $actual['settings']['spacing'] );
	}

	/**
	 * @ticket 52991
	 */
	public function test_get_editor_settings_custom_units_can_be_filtered() {
		add_theme_support( 'custom-units', 'rem', 'em' );
		$input = get_default_block_editor_settings();

		$expected = array(
			'units'         => array( 'rem', 'em' ),
			'customPadding' => false,
		);

		$actual = WP_Theme_JSON::get_from_editor_settings( $input );

		$this->assertSameSetsWithIndex( $expected, $actual['settings']['spacing'] );
	}

}
