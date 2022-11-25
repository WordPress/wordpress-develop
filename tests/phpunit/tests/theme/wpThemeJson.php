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
 *
 * @covers WP_Theme_JSON
 */
class Tests_Theme_wpThemeJson extends WP_UnitTestCase {

	/**
	 * @ticket 52991
	 * @ticket 54336
	 */
	public function test_get_settings() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'color'       => array(
						'custom' => false,
					),
					'layout'      => array(
						'contentSize' => 'value',
						'invalid/key' => 'value',
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
			'layout' => array(
				'contentSize' => 'value',
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

	/**
	 * @ticket 53397
	 */
	public function test_get_settings_presets_are_keyed_by_origin() {
		$default_origin = new WP_Theme_JSON(
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
			'default'
		);
		$no_origin      = new WP_Theme_JSON(
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

		$actual_default   = $default_origin->get_raw_data();
		$actual_no_origin = $no_origin->get_raw_data();

		$expected_default   = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(
				'color'  => array(
					'palette' => array(
						'default' => array(
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
								'default' => array(
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

		$this->assertEqualSetsWithIndex( $expected_default, $actual_default );
		$this->assertEqualSetsWithIndex( $expected_no_origin, $actual_no_origin );
	}

	function test_get_settings_appearance_true_opts_in() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'appearanceTools' => true,
					'spacing'         => array(
						'blockGap' => false, // This should override appearanceTools.
					),
					'blocks'          => array(
						'core/paragraph' => array(
							'typography' => array(
								'lineHeight' => false,
							),
						),
						'core/group'     => array(
							'appearanceTools' => true,
							'typography'      => array(
								'lineHeight' => false, // This should override appearanceTools.
							),
							'spacing'         => array(
								'blockGap' => null,
							),
						),
					),
				),
			)
		);

		$actual   = $theme_json->get_settings();
		$expected = array(
			'border'     => array(
				'width'  => true,
				'style'  => true,
				'radius' => true,
				'color'  => true,
			),
			'color'      => array(
				'link' => true,
			),
			'spacing'    => array(
				'blockGap' => false,
				'margin'   => true,
				'padding'  => true,
			),
			'typography' => array(
				'lineHeight' => true,
			),
			'blocks'     => array(
				'core/paragraph' => array(
					'typography' => array(
						'lineHeight' => false,
					),
				),
				'core/group'     => array(
					'border'     => array(
						'width'  => true,
						'style'  => true,
						'radius' => true,
						'color'  => true,
					),
					'color'      => array(
						'link' => true,
					),
					'spacing'    => array(
						'blockGap' => false,
						'margin'   => true,
						'padding'  => true,
					),
					'typography' => array(
						'lineHeight' => false,
					),
				),
			),
		);

		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	function test_get_settings_appearance_false_does_not_opt_in() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'appearanceTools' => false,
					'border'          => array(
						'width' => true,
					),
					'blocks'          => array(
						'core/paragraph' => array(
							'typography' => array(
								'lineHeight' => false,
							),
						),
						'core/group'     => array(
							'typography' => array(
								'lineHeight' => false,
							),
						),
					),
				),
			)
		);

		$actual   = $theme_json->get_settings();
		$expected = array(
			'appearanceTools' => false,
			'border'          => array(
				'width' => true,
			),
			'blocks'          => array(
				'core/paragraph' => array(
					'typography' => array(
						'lineHeight' => false,
					),
				),
				'core/group'     => array(
					'typography' => array(
						'lineHeight' => false,
					),
				),
			),
		);

		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	/**
	 * @ticket 54336
	 */
	public function test_get_stylesheet_support_for_shorthand_and_longhand_values() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version' => WP_Theme_JSON::LATEST_SCHEMA,
				'styles'  => array(
					'blocks' => array(
						'core/group' => array(
							'border'  => array(
								'radius' => '10px',
							),
							'spacing' => array(
								'padding' => '24px',
								'margin'  => '1em',
							),
						),
						'core/image' => array(
							'border'  => array(
								'radius' => array(
									'topLeft'     => '10px',
									'bottomRight' => '1em',
								),
							),
							'spacing' => array(
								'padding' => array(
									'top' => '15px',
								),
								'margin'  => array(
									'bottom' => '30px',
								),
							),
						),
					),
				),
			)
		);

		$styles = 'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }.wp-block-group{border-radius: 10px;margin: 1em;padding: 24px;}.wp-block-image{margin-bottom: 30px;padding-top: 15px;}.wp-block-image img, .wp-block-image .wp-block-image__crop-area{border-top-left-radius: 10px;border-bottom-right-radius: 1em;}';
		$this->assertSame( $styles, $theme_json->get_stylesheet() );
		$this->assertSame( $styles, $theme_json->get_stylesheet( array( 'styles' ) ) );
	}

	/**
	 * @ticket 54336
	 */
	public function test_get_stylesheet_skips_disabled_protected_properties() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'spacing' => array(
						'blockGap' => null,
					),
				),
				'styles'   => array(
					'spacing' => array(
						'blockGap' => '1em',
					),
					'blocks'  => array(
						'core/columns' => array(
							'spacing' => array(
								'blockGap' => '24px',
							),
						),
					),
				),
			)
		);

		$expected = 'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }';
		$this->assertSame( $expected, $theme_json->get_stylesheet() );
		$this->assertSame( $expected, $theme_json->get_stylesheet( array( 'styles' ) ) );
	}

	/**
	 * @ticket 54336
	 */
	public function test_get_stylesheet_renders_enabled_protected_properties() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'spacing' => array(
						'blockGap' => true,
					),
				),
				'styles'   => array(
					'spacing' => array(
						'blockGap' => '1em',
					),
				),
			)
		);

		$expected = 'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }.wp-site-blocks > * { margin-block-start: 0; margin-block-end: 0; }.wp-site-blocks > * + * { margin-block-start: 1em; }body { --wp--style--block-gap: 1em; }';
		$this->assertSame( $expected, $theme_json->get_stylesheet() );
		$this->assertSame( $expected, $theme_json->get_stylesheet( array( 'styles' ) ) );
	}

	/**
	 * @ticket 53175
	 * @ticket 54336
	 * @ticket 56611
	 */
	public function test_get_stylesheet() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'color'      => array(
						'text'      => 'value',
						'palette'   => array(
							array(
								'slug'  => 'grey',
								'color' => 'grey',
							),
						),
						'gradients' => array(
							array(
								'gradient' => 'linear-gradient(135deg,rgba(0,0,0) 0%,rgb(0,0,0) 100%)',
								'name'     => 'Custom gradient',
								'slug'     => 'custom-gradient',
							),
						),
						'duotone'   => array(
							array(
								'colors' => array( '#333333', '#aaaaaa' ),
								'name'   => 'Custom Duotone',
								'slug'   => 'custom-duotone',
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
						'link'   => array(
							'color' => array(
								'text'       => '#111',
								'background' => '#333',
							),
						),
						'button' => array(
							'shadow' => '10px 10px 5px 0px rgba(0,0,0,0.66)',
						),
					),
					'blocks'   => array(
						'core/group'     => array(
							'color'    => array(
								'gradient' => 'var:preset|gradient|custom-gradient',
							),
							'border'   => array(
								'radius' => '10px',
							),
							'elements' => array(
								'link' => array(
									'color' => array(
										'text' => '#111',
									),
								),
							),
							'spacing'  => array(
								'padding' => '24px',
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
						'core/image'     => array(
							'border'  => array(
								'radius' => array(
									'topLeft'     => '10px',
									'bottomRight' => '1em',
								),
							),
							'spacing' => array(
								'margin' => array(
									'bottom' => '30px',
								),
							),
							'filter'  => array(
								'duotone' => 'var:preset|duotone|custom-duotone',
							),
						),
					),
					'spacing'  => array(
						'blockGap' => '24px',
					),
				),
				'misc'     => 'value',
			)
		);

		$variables = "body{--wp--preset--color--grey: grey;--wp--preset--gradient--custom-gradient: linear-gradient(135deg,rgba(0,0,0) 0%,rgb(0,0,0) 100%);--wp--preset--duotone--custom-duotone: url('#wp-duotone-custom-duotone');--wp--preset--font-family--small: 14px;--wp--preset--font-family--big: 41px;}.wp-block-group{--wp--custom--base-font: 16;--wp--custom--line-height--small: 1.2;--wp--custom--line-height--medium: 1.4;--wp--custom--line-height--large: 1.8;}";
		$styles    = 'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }body{color: var(--wp--preset--color--grey);}a:where(:not(.wp-element-button)){background-color: #333;color: #111;}.wp-element-button, .wp-block-button__link{box-shadow: 10px 10px 5px 0px rgba(0,0,0,0.66);}.wp-block-group{background: var(--wp--preset--gradient--custom-gradient);border-radius: 10px;padding: 24px;}.wp-block-group a:where(:not(.wp-element-button)){color: #111;}h1,h2,h3,h4,h5,h6{color: #123456;}h1 a:where(:not(.wp-element-button)),h2 a:where(:not(.wp-element-button)),h3 a:where(:not(.wp-element-button)),h4 a:where(:not(.wp-element-button)),h5 a:where(:not(.wp-element-button)),h6 a:where(:not(.wp-element-button)){background-color: #333;color: #111;font-size: 60px;}.wp-block-post-date{color: #123456;}.wp-block-post-date a:where(:not(.wp-element-button)){background-color: #777;color: #555;}.wp-block-image{margin-bottom: 30px;}.wp-block-image img, .wp-block-image .components-placeholder{filter: var(--wp--preset--duotone--custom-duotone);}.wp-block-image img, .wp-block-image .wp-block-image__crop-area{border-top-left-radius: 10px;border-bottom-right-radius: 1em;}';
		$presets   = '.has-grey-color{color: var(--wp--preset--color--grey) !important;}.has-grey-background-color{background-color: var(--wp--preset--color--grey) !important;}.has-grey-border-color{border-color: var(--wp--preset--color--grey) !important;}.has-custom-gradient-gradient-background{background: var(--wp--preset--gradient--custom-gradient) !important;}.has-small-font-family{font-family: var(--wp--preset--font-family--small) !important;}.has-big-font-family{font-family: var(--wp--preset--font-family--big) !important;}';
		$all       = $variables . $styles . $presets;
		$this->assertSame( $all, $theme_json->get_stylesheet() );
		$this->assertSame( $styles, $theme_json->get_stylesheet( array( 'styles' ) ) );
		$this->assertSame( $presets, $theme_json->get_stylesheet( array( 'presets' ) ) );
		$this->assertSame( $variables, $theme_json->get_stylesheet( array( 'variables' ) ) );
	}

	/**
	 * @ticket 52991
	 * @ticket 54336
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
			'h1.has-white-color,h2.has-white-color,h3.has-white-color,h4.has-white-color,h5.has-white-color,h6.has-white-color{color: var(--wp--preset--color--white) !important;}h1.has-white-background-color,h2.has-white-background-color,h3.has-white-background-color,h4.has-white-background-color,h5.has-white-background-color,h6.has-white-background-color{background-color: var(--wp--preset--color--white) !important;}h1.has-white-border-color,h2.has-white-border-color,h3.has-white-border-color,h4.has-white-border-color,h5.has-white-border-color,h6.has-white-border-color{border-color: var(--wp--preset--color--white) !important;}',
			$theme_json->get_stylesheet( array( 'presets' ) )
		);
	}

	/**
	 * @ticket 53175
	 * @ticket 54336
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

		$styles    = 'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }.wp-block-group{color: red;}';
		$presets   = '.wp-block-group.has-grey-color{color: var(--wp--preset--color--grey) !important;}.wp-block-group.has-grey-background-color{background-color: var(--wp--preset--color--grey) !important;}.wp-block-group.has-grey-border-color{border-color: var(--wp--preset--color--grey) !important;}';
		$variables = '.wp-block-group{--wp--preset--color--grey: grey;}';
		$all       = $variables . $styles . $presets;
		$this->assertSame( $all, $theme_json->get_stylesheet() );
		$this->assertSame( $styles, $theme_json->get_stylesheet( array( 'styles' ) ) );
		$this->assertSame( $presets, $theme_json->get_stylesheet( array( 'presets' ) ) );
		$this->assertSame( $variables, $theme_json->get_stylesheet( array( 'variables' ) ) );
	}

	/**
	 * @ticket 54336
	 */
	public function test_get_stylesheet_generates_proper_classes_and_css_vars_from_slugs() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'color'  => array(
						'palette' => array(
							array(
								'slug'  => 'grey',
								'color' => 'grey',
							),
							array(
								'slug'  => 'dark grey',
								'color' => 'grey',
							),
							array(
								'slug'  => 'light-grey',
								'color' => 'grey',
							),
							array(
								'slug'  => 'white2black',
								'color' => 'grey',
							),
						),
					),
					'custom' => array(
						'white2black' => 'value',
					),
				),
			)
		);

		$this->assertSame(
			'.has-grey-color{color: var(--wp--preset--color--grey) !important;}.has-dark-grey-color{color: var(--wp--preset--color--dark-grey) !important;}.has-light-grey-color{color: var(--wp--preset--color--light-grey) !important;}.has-white-2-black-color{color: var(--wp--preset--color--white-2-black) !important;}.has-grey-background-color{background-color: var(--wp--preset--color--grey) !important;}.has-dark-grey-background-color{background-color: var(--wp--preset--color--dark-grey) !important;}.has-light-grey-background-color{background-color: var(--wp--preset--color--light-grey) !important;}.has-white-2-black-background-color{background-color: var(--wp--preset--color--white-2-black) !important;}.has-grey-border-color{border-color: var(--wp--preset--color--grey) !important;}.has-dark-grey-border-color{border-color: var(--wp--preset--color--dark-grey) !important;}.has-light-grey-border-color{border-color: var(--wp--preset--color--light-grey) !important;}.has-white-2-black-border-color{border-color: var(--wp--preset--color--white-2-black) !important;}',
			$theme_json->get_stylesheet( array( 'presets' ) )
		);
		$this->assertSame(
			'body{--wp--preset--color--grey: grey;--wp--preset--color--dark-grey: grey;--wp--preset--color--light-grey: grey;--wp--preset--color--white-2-black: grey;--wp--custom--white-2-black: value;}',
			$theme_json->get_stylesheet( array( 'variables' ) )
		);

	}

	/**
	 * @ticket 53175
	 * @ticket 54336
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
			'default'
		);

		$this->assertSame(
			'body{--wp--preset--color--grey: grey;}body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }p{background-color: blue;color: red;font-size: 12px;line-height: 1.3;}.has-grey-color{color: var(--wp--preset--color--grey) !important;}.has-grey-background-color{background-color: var(--wp--preset--color--grey) !important;}.has-grey-border-color{border-color: var(--wp--preset--color--grey) !important;}',
			$theme_json->get_stylesheet()
		);
	}

	/**
	 * @ticket 56467
	 */
	public function test_get_stylesheet_handles_whitelisted_element_pseudo_selectors() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version' => WP_Theme_JSON::LATEST_SCHEMA,
				'styles'  => array(
					'elements' => array(
						'link' => array(
							'color'  => array(
								'text'       => 'green',
								'background' => 'red',
							),
							':hover' => array(
								'color'      => array(
									'text'       => 'red',
									'background' => 'green',
								),
								'typography' => array(
									'textTransform' => 'uppercase',
									'fontSize'      => '10em',
								),
							),
							':focus' => array(
								'color' => array(
									'text'       => 'yellow',
									'background' => 'black',
								),
							),
						),
					),
				),
			)
		);

		$base_styles = 'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }';

		$element_styles = 'a:where(:not(.wp-element-button)){background-color: red;color: green;}a:where(:not(.wp-element-button)):hover{background-color: green;color: red;font-size: 10em;text-transform: uppercase;}a:where(:not(.wp-element-button)):focus{background-color: black;color: yellow;}';

		$expected = $base_styles . $element_styles;

		$this->assertSame( $expected, $theme_json->get_stylesheet() );
		$this->assertSame( $expected, $theme_json->get_stylesheet( array( 'styles' ) ) );
	}

	/**
	 * @ticket 56467
	 */
	public function test_get_stylesheet_handles_only_pseudo_selector_rules_for_given_property() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version' => WP_Theme_JSON::LATEST_SCHEMA,
				'styles'  => array(
					'elements' => array(
						'link' => array(
							':hover' => array(
								'color'      => array(
									'text'       => 'red',
									'background' => 'green',
								),
								'typography' => array(
									'textTransform' => 'uppercase',
									'fontSize'      => '10em',
								),
							),
							':focus' => array(
								'color' => array(
									'text'       => 'yellow',
									'background' => 'black',
								),
							),
						),
					),
				),
			)
		);

		$base_styles = 'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }';

		$element_styles = 'a:where(:not(.wp-element-button)):hover{background-color: green;color: red;font-size: 10em;text-transform: uppercase;}a:where(:not(.wp-element-button)):focus{background-color: black;color: yellow;}';

		$expected = $base_styles . $element_styles;

		$this->assertSame( $expected, $theme_json->get_stylesheet() );
		$this->assertSame( $expected, $theme_json->get_stylesheet( array( 'styles' ) ) );
	}

	/**
	 * @ticket 56467
	 */
	public function test_get_stylesheet_ignores_pseudo_selectors_on_non_whitelisted_elements() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version' => WP_Theme_JSON::LATEST_SCHEMA,
				'styles'  => array(
					'elements' => array(
						'h4' => array(
							'color'  => array(
								'text'       => 'green',
								'background' => 'red',
							),
							':hover' => array(
								'color' => array(
									'text'       => 'red',
									'background' => 'green',
								),
							),
							':focus' => array(
								'color' => array(
									'text'       => 'yellow',
									'background' => 'black',
								),
							),
						),
					),
				),
			)
		);

		$base_styles = 'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }';

		$element_styles = 'h4{background-color: red;color: green;}';

		$expected = $base_styles . $element_styles;

		$this->assertSame( $expected, $theme_json->get_stylesheet() );
		$this->assertSame( $expected, $theme_json->get_stylesheet( array( 'styles' ) ) );
	}

	/**
	 * @ticket 56467
	 */
	public function test_get_stylesheet_ignores_non_whitelisted_pseudo_selectors() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version' => WP_Theme_JSON::LATEST_SCHEMA,
				'styles'  => array(
					'elements' => array(
						'link' => array(
							'color'     => array(
								'text'       => 'green',
								'background' => 'red',
							),
							':hover'    => array(
								'color' => array(
									'text'       => 'red',
									'background' => 'green',
								),
							),
							':levitate' => array(
								'color' => array(
									'text'       => 'yellow',
									'background' => 'black',
								),
							),
						),
					),
				),
			)
		);

		$base_styles = 'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }';

		$element_styles = 'a:where(:not(.wp-element-button)){background-color: red;color: green;}a:where(:not(.wp-element-button)):hover{background-color: green;color: red;}';

		$expected = $base_styles . $element_styles;

		$this->assertSame( $expected, $theme_json->get_stylesheet() );
		$this->assertSame( $expected, $theme_json->get_stylesheet( array( 'styles' ) ) );
		$this->assertStringNotContainsString( 'a:levitate{', $theme_json->get_stylesheet( array( 'styles' ) ) );
	}

	/**
	 * @ticket 56467
	 */
	public function test_get_stylesheet_handles_priority_of_elements_vs_block_elements_pseudo_selectors() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version' => WP_Theme_JSON::LATEST_SCHEMA,
				'styles'  => array(
					'blocks' => array(
						'core/group' => array(
							'elements' => array(
								'link' => array(
									'color'  => array(
										'text'       => 'green',
										'background' => 'red',
									),
									':hover' => array(
										'color'      => array(
											'text'       => 'red',
											'background' => 'green',
										),
										'typography' => array(
											'textTransform' => 'uppercase',
											'fontSize' => '10em',
										),
									),
									':focus' => array(
										'color' => array(
											'text'       => 'yellow',
											'background' => 'black',
										),
									),
								),
							),
						),
					),
				),
			)
		);

		$base_styles = 'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }';

		$element_styles = '.wp-block-group a:where(:not(.wp-element-button)){background-color: red;color: green;}.wp-block-group a:where(:not(.wp-element-button)):hover{background-color: green;color: red;font-size: 10em;text-transform: uppercase;}.wp-block-group a:where(:not(.wp-element-button)):focus{background-color: black;color: yellow;}';

		$expected = $base_styles . $element_styles;

		$this->assertSame( $expected, $theme_json->get_stylesheet() );
		$this->assertSame( $expected, $theme_json->get_stylesheet( array( 'styles' ) ) );
	}

	/**
	 * @ticket 56467
	 */
	public function test_get_stylesheet_handles_whitelisted_block_level_element_pseudo_selectors() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version' => WP_Theme_JSON::LATEST_SCHEMA,
				'styles'  => array(
					'elements' => array(
						'link' => array(
							'color'  => array(
								'text'       => 'green',
								'background' => 'red',
							),
							':hover' => array(
								'color' => array(
									'text'       => 'red',
									'background' => 'green',
								),
							),
						),
					),
					'blocks'   => array(
						'core/group' => array(
							'elements' => array(
								'link' => array(
									':hover' => array(
										'color' => array(
											'text'       => 'yellow',
											'background' => 'black',
										),
									),
								),
							),
						),
					),
				),
			)
		);

		$base_styles = 'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }';

		$element_styles = 'a:where(:not(.wp-element-button)){background-color: red;color: green;}a:where(:not(.wp-element-button)):hover{background-color: green;color: red;}.wp-block-group a:where(:not(.wp-element-button)):hover{background-color: black;color: yellow;}';

		$expected = $base_styles . $element_styles;

		$this->assertSame( $expected, $theme_json->get_stylesheet() );
		$this->assertSame( $expected, $theme_json->get_stylesheet( array( 'styles' ) ) );
	}

	/**
	 * @ticket 52991
	 * @ticket 54336
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
					'fontSizes'    => array(
						'theme' => array(
							array(
								'slug' => 'fontSize',
								'size' => 'fontSize',
							),
						),
					),
					'fontFamilies' => array(
						'theme' => array(
							array(
								'slug'       => 'fontFamily',
								'fontFamily' => 'fontFamily',
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
	 * @ticket 53175
	 * @ticket 54336
	 */
	public function test_merge_incoming_data_empty_presets() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'color'      => array(
						'duotone'   => array(
							array(
								'slug'   => 'value',
								'colors' => array( 'red', 'green' ),
							),
						),
						'gradients' => array(
							array(
								'slug'     => 'gradient',
								'gradient' => 'gradient',
							),
						),
						'palette'   => array(
							array(
								'slug'  => 'red',
								'color' => 'red',
							),
						),
					),
					'spacing'    => array(
						'units' => array( 'px', 'em' ),
					),
					'typography' => array(
						'fontSizes' => array(
							array(
								'slug'  => 'size',
								'value' => 'size',
							),
						),
					),
				),
			)
		);

		$theme_json->merge(
			new WP_Theme_JSON(
				array(
					'version'  => WP_Theme_JSON::LATEST_SCHEMA,
					'settings' => array(
						'color'      => array(
							'duotone'   => array(),
							'gradients' => array(),
							'palette'   => array(),
						),
						'spacing'    => array(
							'units' => array(),
						),
						'typography' => array(
							'fontSizes' => array(),
						),
					),
				)
			)
		);

		$actual   = $theme_json->get_raw_data();
		$expected = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(
				'color'      => array(
					'duotone'   => array(
						'theme' => array(),
					),
					'gradients' => array(
						'theme' => array(),
					),
					'palette'   => array(
						'theme' => array(),
					),
				),
				'spacing'    => array(
					'units' => array(),
				),
				'typography' => array(
					'fontSizes' => array(
						'theme' => array(),
					),
				),
			),
		);

		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	/**
	 * @ticket 53175
	 * @ticket 54336
	 */
	public function test_merge_incoming_data_null_presets() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'color'      => array(
						'duotone'   => array(
							array(
								'slug'   => 'value',
								'colors' => array( 'red', 'green' ),
							),
						),
						'gradients' => array(
							array(
								'slug'     => 'gradient',
								'gradient' => 'gradient',
							),
						),
						'palette'   => array(
							array(
								'slug'  => 'red',
								'color' => 'red',
							),
						),
					),
					'spacing'    => array(
						'units' => array( 'px', 'em' ),
					),
					'typography' => array(
						'fontSizes' => array(
							array(
								'slug'  => 'size',
								'value' => 'size',
							),
						),
					),
				),
			)
		);

		$theme_json->merge(
			new WP_Theme_JSON(
				array(
					'version'  => WP_Theme_JSON::LATEST_SCHEMA,
					'settings' => array(
						'color'      => array(
							'custom' => false,
						),
						'spacing'    => array(
							'margin' => false,
						),
						'typography' => array(
							'lineHeight' => false,
						),
					),
				)
			)
		);

		$actual   = $theme_json->get_raw_data();
		$expected = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(
				'color'      => array(
					'custom'    => false,
					'duotone'   => array(
						'theme' => array(
							array(
								'slug'   => 'value',
								'colors' => array( 'red', 'green' ),
							),
						),
					),
					'gradients' => array(
						'theme' => array(
							array(
								'slug'     => 'gradient',
								'gradient' => 'gradient',
							),
						),
					),
					'palette'   => array(
						'theme' => array(
							array(
								'slug'  => 'red',
								'color' => 'red',
							),
						),
					),
				),
				'spacing'    => array(
					'margin' => false,
					'units'  => array( 'px', 'em' ),
				),
				'typography' => array(
					'lineHeight' => false,
					'fontSizes'  => array(
						'theme' => array(
							array(
								'slug'  => 'size',
								'value' => 'size',
							),
						),
					),
				),
			),
		);

		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	public function test_merge_incoming_data_color_presets_with_same_slugs_as_default_are_removed() {
		$defaults = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'color'  => array(
						'defaultPalette' => true,
						'palette'        => array(
							array(
								'slug'  => 'red',
								'color' => 'red',
								'name'  => 'Red',
							),
							array(
								'slug'  => 'green',
								'color' => 'green',
								'name'  => 'Green',
							),
						),
					),
					'blocks' => array(
						'core/paragraph' => array(
							'color' => array(
								'palette' => array(
									array(
										'slug'  => 'blue',
										'color' => 'blue',
										'name'  => 'Blue',
									),
								),
							),
						),
					),
				),
			),
			'default'
		);
		$theme    = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'color'  => array(
						'palette' => array(
							array(
								'slug'  => 'pink',
								'color' => 'pink',
								'name'  => 'Pink',
							),
							array(
								'slug'  => 'green',
								'color' => 'green',
								'name'  => 'Greenish',
							),
						),
					),
					'blocks' => array(
						'core/paragraph' => array(
							'color' => array(
								'palette' => array(
									array(
										'slug'  => 'blue',
										'color' => 'blue',
										'name'  => 'Bluish',
									),
									array(
										'slug'  => 'yellow',
										'color' => 'yellow',
										'name'  => 'Yellow',
									),
									array(
										'slug'  => 'green',
										'color' => 'green',
										'name'  => 'Block Green',
									),
								),
							),
						),
					),
				),
			)
		);

		$expected = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(
				'color'  => array(
					'palette'        => array(
						'default' => array(
							array(
								'slug'  => 'red',
								'color' => 'red',
								'name'  => 'Red',
							),
							array(
								'slug'  => 'green',
								'color' => 'green',
								'name'  => 'Green',
							),
						),
						'theme'   => array(
							array(
								'slug'  => 'pink',
								'color' => 'pink',
								'name'  => 'Pink',
							),
						),
					),
					'defaultPalette' => true,
				),
				'blocks' => array(
					'core/paragraph' => array(
						'color' => array(
							'palette' => array(
								'default' => array(
									array(
										'slug'  => 'blue',
										'color' => 'blue',
										'name'  => 'Blue',
									),
								),
								'theme'   => array(
									array(
										'slug'  => 'yellow',
										'color' => 'yellow',
										'name'  => 'Yellow',
									),
								),
							),
						),
					),
				),
			),
		);

		$defaults->merge( $theme );
		$actual = $defaults->get_raw_data();

		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	public function test_merge_incoming_data_color_presets_with_same_slugs_as_default_are_not_removed_if_defaults_are_disabled() {
		$defaults = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'color'  => array(
						'defaultPalette' => true, // Emulate the defaults from core theme.json.
						'palette'        => array(
							array(
								'slug'  => 'red',
								'color' => 'red',
								'name'  => 'Red',
							),
							array(
								'slug'  => 'green',
								'color' => 'green',
								'name'  => 'Green',
							),
						),
					),
					'blocks' => array(
						'core/paragraph' => array(
							'color' => array(
								'palette' => array(
									array(
										'slug'  => 'blue',
										'color' => 'blue',
										'name'  => 'Blue',
									),
								),
							),
						),
					),
				),
			),
			'default'
		);
		$theme    = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'color'  => array(
						'defaultPalette' => false,
						'palette'        => array(
							array(
								'slug'  => 'pink',
								'color' => 'pink',
								'name'  => 'Pink',
							),
							array(
								'slug'  => 'green',
								'color' => 'green',
								'name'  => 'Greenish',
							),
						),
					),
					'blocks' => array(
						'core/paragraph' => array(
							'color' => array(
								'palette' => array(
									array(
										'slug'  => 'blue',
										'color' => 'blue',
										'name'  => 'Bluish',
									),
									array(
										'slug'  => 'yellow',
										'color' => 'yellow',
										'name'  => 'Yellow',
									),
									array(
										'slug'  => 'green',
										'color' => 'green',
										'name'  => 'Block Green',
									),
								),
							),
						),
					),
				),
			)
		);

		$expected = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(
				'color'  => array(
					'defaultPalette' => false,
					'palette'        => array(
						'default' => array(
							array(
								'slug'  => 'red',
								'color' => 'red',
								'name'  => 'Red',
							),
							array(
								'slug'  => 'green',
								'color' => 'green',
								'name'  => 'Green',
							),
						),
						'theme'   => array(
							array(
								'slug'  => 'pink',
								'color' => 'pink',
								'name'  => 'Pink',
							),
							array(
								'slug'  => 'green',
								'color' => 'green',
								'name'  => 'Greenish',
							),
						),
					),
				),
				'blocks' => array(
					'core/paragraph' => array(
						'color' => array(
							'palette' => array(
								'default' => array(
									array(
										'slug'  => 'blue',
										'color' => 'blue',
										'name'  => 'Blue',
									),
								),
								'theme'   => array(
									array(
										'slug'  => 'blue',
										'color' => 'blue',
										'name'  => 'Bluish',
									),
									array(
										'slug'  => 'yellow',
										'color' => 'yellow',
										'name'  => 'Yellow',
									),
									array(
										'slug'  => 'green',
										'color' => 'green',
										'name'  => 'Block Green',
									),
								),
							),
						),
					),
				),
			),
		);

		$defaults->merge( $theme );
		$actual = $defaults->get_raw_data();

		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	/**
	 * @ticket 54640
	 */
	public function test_merge_incoming_data_presets_use_default_names() {
		$defaults   = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'typography' => array(
						'fontSizes' => array(
							array(
								'name' => 'Small',
								'slug' => 'small',
								'size' => '12px',
							),
							array(
								'name' => 'Large',
								'slug' => 'large',
								'size' => '20px',
							),
						),
					),
				),
			),
			'default'
		);
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'typography' => array(
						'fontSizes' => array(
							array(
								'slug' => 'small',
								'size' => '1.1rem',
							),
							array(
								'slug' => 'large',
								'size' => '1.75rem',
							),
							array(
								'name' => 'Huge',
								'slug' => 'huge',
								'size' => '3rem',
							),
						),
					),
				),
			),
			'theme'
		);
		$expected   = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(
				'typography' => array(
					'fontSizes' => array(
						'default' => array(
							array(
								'name' => 'Small',
								'slug' => 'small',
								'size' => '12px',
							),
							array(
								'name' => 'Large',
								'slug' => 'large',
								'size' => '20px',
							),
						),
						'theme'   => array(
							array(
								'slug' => 'small',
								'size' => '1.1rem',
								'name' => 'Small',
							),
							array(
								'slug' => 'large',
								'size' => '1.75rem',
								'name' => 'Large',
							),
							array(
								'name' => 'Huge',
								'slug' => 'huge',
								'size' => '3rem',
							),
						),
					),
				),
			),
		);
		$defaults->merge( $theme_json );
		$actual = $defaults->get_raw_data();
		$this->assertSameSetsWithIndex( $expected, $actual );
	}

	/**
	 * @ticket 54336
	 */
	public function test_remove_insecure_properties_removes_unsafe_styles() {
		$actual = WP_Theme_JSON::remove_insecure_properties(
			array(
				'version' => WP_Theme_JSON::LATEST_SCHEMA,
				'styles'  => array(
					'color'    => array(
						'gradient' => 'url(\'data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPScxMCcgaGVpZ2h0PScxMCc+PHNjcmlwdD5hbGVydCgnb2snKTwvc2NyaXB0PjxsaW5lYXJHcmFkaWVudCBpZD0nZ3JhZGllbnQnPjxzdG9wIG9mZnNldD0nMTAlJyBzdG9wLWNvbG9yPScjRjAwJy8+PHN0b3Agb2Zmc2V0PSc5MCUnIHN0b3AtY29sb3I9JyNmY2MnLz4gPC9saW5lYXJHcmFkaWVudD48cmVjdCBmaWxsPSd1cmwoI2dyYWRpZW50KScgeD0nMCcgeT0nMCcgd2lkdGg9JzEwMCUnIGhlaWdodD0nMTAwJScvPjwvc3ZnPg==\')',
						'text'     => 'var:preset|color|dark-red',
					),
					'elements' => array(
						'link' => array(
							'color' => array(
								'gradient'   => 'url(\'data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPScxMCcgaGVpZ2h0PScxMCc+PHNjcmlwdD5hbGVydCgnb2snKTwvc2NyaXB0PjxsaW5lYXJHcmFkaWVudCBpZD0nZ3JhZGllbnQnPjxzdG9wIG9mZnNldD0nMTAlJyBzdG9wLWNvbG9yPScjRjAwJy8+PHN0b3Agb2Zmc2V0PSc5MCUnIHN0b3AtY29sb3I9JyNmY2MnLz4gPC9saW5lYXJHcmFkaWVudD48cmVjdCBmaWxsPSd1cmwoI2dyYWRpZW50KScgeD0nMCcgeT0nMCcgd2lkdGg9JzEwMCUnIGhlaWdodD0nMTAwJScvPjwvc3ZnPg==\')',
								'text'       => 'var:preset|color|dark-pink',
								'background' => 'var:preset|color|dark-red',
							),
						),
					),
					'blocks'   => array(
						'core/image'  => array(
							'filter' => array(
								'duotone' => 'var:preset|duotone|blue-red',
							),
						),
						'core/cover'  => array(
							'filter' => array(
								'duotone' => 'var(--invalid',
							),
						),
						'core/group'  => array(
							'color'    => array(
								'gradient' => 'url(\'data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPScxMCcgaGVpZ2h0PScxMCc+PHNjcmlwdD5hbGVydCgnb2snKTwvc2NyaXB0PjxsaW5lYXJHcmFkaWVudCBpZD0nZ3JhZGllbnQnPjxzdG9wIG9mZnNldD0nMTAlJyBzdG9wLWNvbG9yPScjRjAwJy8+PHN0b3Agb2Zmc2V0PSc5MCUnIHN0b3AtY29sb3I9JyNmY2MnLz4gPC9saW5lYXJHcmFkaWVudD48cmVjdCBmaWxsPSd1cmwoI2dyYWRpZW50KScgeD0nMCcgeT0nMCcgd2lkdGg9JzEwMCUnIGhlaWdodD0nMTAwJScvPjwvc3ZnPg==\')',
								'text'     => 'var:preset|color|dark-gray',
							),
							'elements' => array(
								'link' => array(
									'color' => array(
										'gradient' => 'url(\'data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPScxMCcgaGVpZ2h0PScxMCc+PHNjcmlwdD5hbGVydCgnb2snKTwvc2NyaXB0PjxsaW5lYXJHcmFkaWVudCBpZD0nZ3JhZGllbnQnPjxzdG9wIG9mZnNldD0nMTAlJyBzdG9wLWNvbG9yPScjRjAwJy8+PHN0b3Agb2Zmc2V0PSc5MCUnIHN0b3AtY29sb3I9JyNmY2MnLz4gPC9saW5lYXJHcmFkaWVudD48cmVjdCBmaWxsPSd1cmwoI2dyYWRpZW50KScgeD0nMCcgeT0nMCcgd2lkdGg9JzEwMCUnIGhlaWdodD0nMTAwJScvPjwvc3ZnPg==\')',
										'text'     => 'var:preset|color|dark-pink',
									),
								),
							),
						),
						'invalid/key' => array(
							'background' => 'green',
						),
					),
				),
			)
		);

		$expected = array(
			'version' => WP_Theme_JSON::LATEST_SCHEMA,
			'styles'  => array(
				'color'    => array(
					'text' => 'var:preset|color|dark-red',
				),
				'elements' => array(
					'link' => array(
						'color' => array(
							'text'       => 'var:preset|color|dark-pink',
							'background' => 'var:preset|color|dark-red',
						),
					),
				),
				'blocks'   => array(
					'core/image' => array(
						'filter' => array(
							'duotone' => 'var:preset|duotone|blue-red',
						),
					),
					'core/group' => array(
						'color'    => array(
							'text' => 'var:preset|color|dark-gray',
						),
						'elements' => array(
							'link' => array(
								'color' => array(
									'text' => 'var:preset|color|dark-pink',
								),
							),
						),
					),
				),
			),
		);
		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	/**
	 * @ticket 54336
	 */
	public function test_remove_insecure_properties_removes_unsafe_styles_sub_properties() {
		$actual = WP_Theme_JSON::remove_insecure_properties(
			array(
				'version' => WP_Theme_JSON::LATEST_SCHEMA,
				'styles'  => array(
					'border'   => array(
						'radius' => array(
							'topLeft'     => '6px',
							'topRight'    => 'var(--invalid',
							'bottomRight' => '6px',
							'bottomLeft'  => '6px',
						),
					),
					'spacing'  => array(
						'padding' => array(
							'top'    => '1px',
							'right'  => '1px',
							'bottom' => 'var(--invalid',
							'left'   => '1px',
						),
					),
					'elements' => array(
						'link' => array(
							'spacing' => array(
								'padding' => array(
									'top'    => '2px',
									'right'  => '2px',
									'bottom' => 'var(--invalid',
									'left'   => '2px',
								),
							),
						),
					),
					'blocks'   => array(
						'core/group' => array(
							'border'   => array(
								'radius' => array(
									'topLeft'     => '5px',
									'topRight'    => 'var(--invalid',
									'bottomRight' => '5px',
									'bottomLeft'  => '5px',
								),
							),
							'spacing'  => array(
								'padding' => array(
									'top'    => '3px',
									'right'  => '3px',
									'bottom' => 'var(--invalid',
									'left'   => '3px',
								),
							),
							'elements' => array(
								'link' => array(
									'spacing' => array(
										'padding' => array(
											'top'    => '4px',
											'right'  => '4px',
											'bottom' => 'var(--invalid',
											'left'   => '4px',
										),
									),
								),
							),
						),
					),
				),
			),
			true
		);

		$expected = array(
			'version' => WP_Theme_JSON::LATEST_SCHEMA,
			'styles'  => array(
				'border'   => array(
					'radius' => array(
						'topLeft'     => '6px',
						'bottomRight' => '6px',
						'bottomLeft'  => '6px',
					),
				),
				'spacing'  => array(
					'padding' => array(
						'top'   => '1px',
						'right' => '1px',
						'left'  => '1px',
					),
				),
				'elements' => array(
					'link' => array(
						'spacing' => array(
							'padding' => array(
								'top'   => '2px',
								'right' => '2px',
								'left'  => '2px',
							),
						),
					),
				),
				'blocks'   => array(
					'core/group' => array(
						'border'   => array(
							'radius' => array(
								'topLeft'     => '5px',
								'bottomRight' => '5px',
								'bottomLeft'  => '5px',
							),
						),
						'spacing'  => array(
							'padding' => array(
								'top'   => '3px',
								'right' => '3px',
								'left'  => '3px',
							),
						),
						'elements' => array(
							'link' => array(
								'spacing' => array(
									'padding' => array(
										'top'   => '4px',
										'right' => '4px',
										'left'  => '4px',
									),
								),
							),
						),
					),
				),
			),
		);
		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	/**
	 * @ticket 54336
	 */
	public function test_remove_insecure_properties_removes_non_preset_settings() {
		$actual = WP_Theme_JSON::remove_insecure_properties(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'color'   => array(
						'custom'  => true,
						'palette' => array(
							'custom' => array(
								array(
									'name'  => 'Red',
									'slug'  => 'red',
									'color' => '#ff0000',
								),
								array(
									'name'  => 'Green',
									'slug'  => 'green',
									'color' => '#00ff00',
								),
								array(
									'name'  => 'Blue',
									'slug'  => 'blue',
									'color' => '#0000ff',
								),
							),
						),
					),
					'spacing' => array(
						'padding' => false,
					),
					'blocks'  => array(
						'core/group' => array(
							'color'   => array(
								'custom'  => true,
								'palette' => array(
									'custom' => array(
										array(
											'name'  => 'Yellow',
											'slug'  => 'yellow',
											'color' => '#ff0000',
										),
										array(
											'name'  => 'Pink',
											'slug'  => 'pink',
											'color' => '#00ff00',
										),
										array(
											'name'  => 'Orange',
											'slug'  => 'orange',
											'color' => '#0000ff',
										),
									),
								),
							),
							'spacing' => array(
								'padding' => false,
							),
						),
					),
				),
			)
		);

		$expected = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(
				'color'  => array(
					'palette' => array(
						'custom' => array(
							array(
								'name'  => 'Red',
								'slug'  => 'red',
								'color' => '#ff0000',
							),
							array(
								'name'  => 'Green',
								'slug'  => 'green',
								'color' => '#00ff00',
							),
							array(
								'name'  => 'Blue',
								'slug'  => 'blue',
								'color' => '#0000ff',
							),
						),
					),
				),
				'blocks' => array(
					'core/group' => array(
						'color' => array(
							'palette' => array(
								'custom' => array(
									array(
										'name'  => 'Yellow',
										'slug'  => 'yellow',
										'color' => '#ff0000',
									),
									array(
										'name'  => 'Pink',
										'slug'  => 'pink',
										'color' => '#00ff00',
									),
									array(
										'name'  => 'Orange',
										'slug'  => 'orange',
										'color' => '#0000ff',
									),
								),
							),
						),
					),
				),
			),
		);
		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	/**
	 * @ticket 54336
	 */
	public function test_remove_insecure_properties_removes_unsafe_preset_settings() {
		$actual = WP_Theme_JSON::remove_insecure_properties(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'color'      => array(
						'palette' => array(
							'custom' => array(
								array(
									'name'  => 'Red/><b>ok</ok>',
									'slug'  => 'red',
									'color' => '#ff0000',
								),
								array(
									'name'  => 'Green',
									'slug'  => 'a" attr',
									'color' => '#00ff00',
								),
								array(
									'name'  => 'Blue',
									'slug'  => 'blue',
									'color' => 'var(--invalid',
								),
								array(
									'name'  => 'Pink',
									'slug'  => 'pink',
									'color' => '#FFC0CB',
								),
							),
						),
					),
					'typography' => array(
						'fontFamilies' => array(
							'custom' => array(
								array(
									'name'       => 'Helvetica Arial/><b>test</b>',
									'slug'       => 'helvetica-arial',
									'fontFamily' => 'Helvetica Neue, Helvetica, Arial, sans-serif',
								),
								array(
									'name'       => 'Geneva',
									'slug'       => 'geneva#asa',
									'fontFamily' => 'Geneva, Tahoma, Verdana, sans-serif',
								),
								array(
									'name'       => 'Cambria',
									'slug'       => 'cambria',
									'fontFamily' => 'Cambria, Georgia, serif',
								),
								array(
									'name'       => 'Helvetica Arial',
									'slug'       => 'helvetica-arial',
									'fontFamily' => 'var(--invalid',
								),
							),
						),
					),
					'blocks'     => array(
						'core/group' => array(
							'color' => array(
								'palette' => array(
									'custom' => array(
										array(
											'name'  => 'Red/><b>ok</ok>',
											'slug'  => 'red',
											'color' => '#ff0000',
										),
										array(
											'name'  => 'Green',
											'slug'  => 'a" attr',
											'color' => '#00ff00',
										),
										array(
											'name'  => 'Blue',
											'slug'  => 'blue',
											'color' => 'var(--invalid',
										),
										array(
											'name'  => 'Pink',
											'slug'  => 'pink',
											'color' => '#FFC0CB',
										),
									),
								),
							),
						),
					),
				),
			)
		);

		$expected = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(
				'color'      => array(
					'palette' => array(
						'custom' => array(
							array(
								'name'  => 'Pink',
								'slug'  => 'pink',
								'color' => '#FFC0CB',
							),
						),
					),
				),
				'typography' => array(
					'fontFamilies' => array(
						'custom' => array(
							array(
								'name'       => 'Cambria',
								'slug'       => 'cambria',
								'fontFamily' => 'Cambria, Georgia, serif',
							),
						),
					),
				),
				'blocks'     => array(
					'core/group' => array(
						'color' => array(
							'palette' => array(
								'custom' => array(
									array(
										'name'  => 'Pink',
										'slug'  => 'pink',
										'color' => '#FFC0CB',
									),
								),
							),
						),
					),
				),
			),
		);
		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	/**
	 * @ticket 54336
	 */
	public function test_remove_insecure_properties_applies_safe_styles() {
		$actual = WP_Theme_JSON::remove_insecure_properties(
			array(
				'version' => WP_Theme_JSON::LATEST_SCHEMA,
				'styles'  => array(
					'color' => array(
						'text' => '#abcabc ', // Trailing space.
					),
				),
			),
			true
		);

		$expected = array(
			'version' => WP_Theme_JSON::LATEST_SCHEMA,
			'styles'  => array(
				'color' => array(
					'text' => '#abcabc ',
				),
			),
		);
		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	/**
	 * @ticket 56467
	 */
	public function test_remove_invalid_element_pseudo_selectors() {
		$actual = WP_Theme_JSON::remove_insecure_properties(
			array(
				'version' => WP_Theme_JSON::LATEST_SCHEMA,
				'styles'  => array(
					'elements' => array(
						'link' => array(
							'color'  => array(
								'text'       => 'hotpink',
								'background' => 'yellow',
							),
							':hover' => array(
								'color' => array(
									'text'       => 'red',
									'background' => 'blue',
								),
							),
						),
					),
				),
			),
			true
		);

		$expected = array(
			'version' => WP_Theme_JSON::LATEST_SCHEMA,
			'styles'  => array(
				'elements' => array(
					'link' => array(
						'color'  => array(
							'text'       => 'hotpink',
							'background' => 'yellow',
						),
						':hover' => array(
							'color' => array(
								'text'       => 'red',
								'background' => 'blue',
							),
						),
					),
				),
			),
		);

		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	/**
	 * @ticket 54336
	 */
	public function test_get_custom_templates() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'         => 1,
				'customTemplates' => array(
					array(
						'name'  => 'page-home',
						'title' => 'Homepage template',
					),
				),
			)
		);

		$page_templates = $theme_json->get_custom_templates();

		$this->assertEqualSetsWithIndex(
			$page_templates,
			array(
				'page-home' => array(
					'title'     => 'Homepage template',
					'postTypes' => array( 'page' ),
				),
			)
		);
	}

	/**
	 * @ticket 54336
	 */
	public function test_get_template_parts() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'       => 1,
				'templateParts' => array(
					array(
						'name'  => 'small-header',
						'title' => 'Small Header',
						'area'  => 'header',
					),
				),
			)
		);

		$template_parts = $theme_json->get_template_parts();

		$this->assertEqualSetsWithIndex(
			$template_parts,
			array(
				'small-header' => array(
					'title' => 'Small Header',
					'area'  => 'header',
				),
			)
		);
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
					'units' => array( 'px', 'em', 'rem', 'vh', 'vw', '%' ),
				),
				'typography' => array(
					'customFontSize' => false,
					'lineHeight'     => true,
					'fontSizes'      => array(
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
	 * @ticket 54336
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
					'customFontSize' => true,
					'lineHeight'     => false,
				),
			),
		);

		$actual = WP_Theme_JSON::get_from_editor_settings( $input );

		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	/**
	 * @ticket 52991
	 * @ticket 54336
	 */
	public function test_get_editor_settings_blank() {
		$expected = array(
			'version'  => WP_Theme_JSON::LATEST_SCHEMA,
			'settings' => array(),
		);
		$actual   = WP_Theme_JSON::get_from_editor_settings( array() );

		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	/**
	 * @ticket 52991
	 * @ticket 54336
	 */
	public function test_get_editor_settings_custom_units_can_be_disabled() {
		add_theme_support( 'custom-units', array() );
		$actual = WP_Theme_JSON::get_from_editor_settings( get_default_block_editor_settings() );
		remove_theme_support( 'custom-units' );

		$expected = array(
			'units'   => array( array() ),
			'padding' => false,
		);

		$this->assertEqualSetsWithIndex( $expected, $actual['settings']['spacing'] );
	}

	/**
	 * @ticket 52991
	 * @ticket 54336
	 */
	public function test_get_editor_settings_custom_units_can_be_enabled() {
		add_theme_support( 'custom-units' );
		$actual = WP_Theme_JSON::get_from_editor_settings( get_default_block_editor_settings() );
		remove_theme_support( 'custom-units' );

		$expected = array(
			'units'   => array( 'px', 'em', 'rem', 'vh', 'vw', '%' ),
			'padding' => false,
		);

		$this->assertEqualSetsWithIndex( $expected, $actual['settings']['spacing'] );
	}

	/**
	 * @ticket 52991
	 * @ticket 54336
	 */
	public function test_get_editor_settings_custom_units_can_be_filtered() {
		add_theme_support( 'custom-units', 'rem', 'em' );
		$actual = WP_Theme_JSON::get_from_editor_settings( get_default_block_editor_settings() );
		remove_theme_support( 'custom-units' );

		$expected = array(
			'units'   => array( 'rem', 'em' ),
			'padding' => false,
		);
		$this->assertEqualSetsWithIndex( $expected, $actual['settings']['spacing'] );
	}

	/**
	 * @ticket 54487
	 */
	public function test_sanitization() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version' => 2,
				'styles'  => array(
					'spacing' => array(
						'blockGap' => 'valid value',
					),
					'blocks'  => array(
						'core/group' => array(
							'spacing' => array(
								'margin'  => 'valid value',
								'display' => 'none',
							),
						),
					),
				),
			)
		);

		$actual   = $theme_json->get_raw_data();
		$expected = array(
			'version' => 2,
			'styles'  => array(
				'spacing' => array(
					'blockGap' => 'valid value',
				),
				'blocks'  => array(
					'core/group' => array(
						'spacing' => array(
							'margin' => 'valid value',
						),
					),
				),
			),
		);

		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	/**
	 * @ticket 55505
	 */
	function test_export_data() {
		$theme = new WP_Theme_JSON(
			array(
				'version'  => 2,
				'settings' => array(
					'color' => array(
						'palette' => array(
							array(
								'slug'  => 'white',
								'color' => 'white',
								'label' => 'White',
							),
							array(
								'slug'  => 'black',
								'color' => 'black',
								'label' => 'Black',
							),
						),
					),
				),
			)
		);
		$user  = new WP_Theme_JSON(
			array(
				'version'  => 2,
				'settings' => array(
					'color' => array(
						'palette' => array(
							array(
								'slug'  => 'white',
								'color' => '#fff',
								'label' => 'User White',
							),
							array(
								'slug'  => 'hotpink',
								'color' => 'hotpink',
								'label' => 'hotpink',
							),
						),
					),
				),
			),
			'custom'
		);

		$theme->merge( $user );
		$actual   = $theme->get_data();
		$expected = array(
			'version'  => 2,
			'settings' => array(
				'color' => array(
					'palette' => array(
						array(
							'slug'  => 'white',
							'color' => '#fff',
							'label' => 'User White',
						),
						array(
							'slug'  => 'black',
							'color' => 'black',
							'label' => 'Black',
						),
						array(
							'slug'  => 'hotpink',
							'color' => 'hotpink',
							'label' => 'hotpink',
						),
					),
				),
			),
		);

		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	/**
	 * @ticket 55505
	 */
	function test_export_data_deals_with_empty_user_data() {
		$theme = new WP_Theme_JSON(
			array(
				'version'  => 2,
				'settings' => array(
					'color' => array(
						'palette' => array(
							array(
								'slug'  => 'white',
								'color' => 'white',
								'label' => 'White',
							),
							array(
								'slug'  => 'black',
								'color' => 'black',
								'label' => 'Black',
							),
						),
					),
				),
			)
		);

		$actual   = $theme->get_data();
		$expected = array(
			'version'  => 2,
			'settings' => array(
				'color' => array(
					'palette' => array(
						array(
							'slug'  => 'white',
							'color' => 'white',
							'label' => 'White',
						),
						array(
							'slug'  => 'black',
							'color' => 'black',
							'label' => 'Black',
						),
					),
				),
			),
		);

		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	/**
	 * @ticket 55505
	 */
	function test_export_data_deals_with_empty_theme_data() {
		$user = new WP_Theme_JSON(
			array(
				'version'  => 2,
				'settings' => array(
					'color' => array(
						'palette' => array(
							array(
								'slug'  => 'white',
								'color' => '#fff',
								'label' => 'User White',
							),
							array(
								'slug'  => 'hotpink',
								'color' => 'hotpink',
								'label' => 'hotpink',
							),
						),
					),
				),
			),
			'custom'
		);

		$actual   = $user->get_data();
		$expected = array(
			'version'  => 2,
			'settings' => array(
				'color' => array(
					'palette' => array(
						array(
							'slug'  => 'white',
							'color' => '#fff',
							'label' => 'User White',
						),
						array(
							'slug'  => 'hotpink',
							'color' => 'hotpink',
							'label' => 'hotpink',
						),
					),
				),
			),
		);

		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	/**
	 * @ticket 55505
	 */
	function test_export_data_deals_with_empty_data() {
		$theme_v2    = new WP_Theme_JSON(
			array(
				'version' => 2,
			),
			'theme'
		);
		$actual_v2   = $theme_v2->get_data();
		$expected_v2 = array( 'version' => 2 );
		$this->assertEqualSetsWithIndex( $expected_v2, $actual_v2 );

		$theme_v1    = new WP_Theme_JSON(
			array(
				'version' => 1,
			),
			'theme'
		);
		$actual_v1   = $theme_v1->get_data();
		$expected_v1 = array( 'version' => 2 );
		$this->assertEqualSetsWithIndex( $expected_v1, $actual_v1 );
	}

	/**
	 * @ticket 55505
	 */
	function test_export_data_sets_appearance_tools() {
		$theme = new WP_Theme_JSON(
			array(
				'version'  => 2,
				'settings' => array(
					'appearanceTools' => true,
					'blocks'          => array(
						'core/paragraph' => array(
							'appearanceTools' => true,
						),
					),
				),
			)
		);

		$actual   = $theme->get_data();
		$expected = array(
			'version'  => 2,
			'settings' => array(
				'appearanceTools' => true,
				'blocks'          => array(
					'core/paragraph' => array(
						'appearanceTools' => true,
					),
				),
			),
		);

		$this->assertEqualSetsWithIndex( $expected, $actual );
	}

	/**
	 * @ticket 56611
	 */
	function test_export_data_sets_use_root_padding_aware_alignments() {
		$theme = new WP_Theme_JSON(
			array(
				'version'  => 2,
				'settings' => array(
					'useRootPaddingAwareAlignments' => true,
					'blocks'                        => array(
						'core/paragraph' => array(
							'useRootPaddingAwareAlignments' => true,
						),
					),
				),
			)
		);

		$actual   = $theme->get_data();
		$expected = array(
			'version'  => 2,
			'settings' => array(
				'useRootPaddingAwareAlignments' => true,
				'blocks'                        => array(
					'core/paragraph' => array(
						'useRootPaddingAwareAlignments' => true,
					),
				),
			),
		);

		$this->assertEqualSetsWithIndex( $expected, $actual );
	}


	/**
	 * @ticket 56467
	 */
	public function test_get_element_class_name_button() {
		$expected = 'wp-element-button';
		$actual   = WP_Theme_JSON::get_element_class_name( 'button' );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @ticket 56467
	 */
	public function test_get_element_class_name_invalid() {
		$expected = '';
		$actual   = WP_Theme_JSON::get_element_class_name( 'unknown-element' );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Testing that dynamic properties in theme.json return the value they reference,
	 * e.g. array( 'ref' => 'styles.color.background' ) => "#ffffff".
	 *
	 * @ticket 56467
	 */
	public function test_get_property_value_valid() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version' => 2,
				'styles'  => array(
					'color'    => array(
						'background' => '#ffffff',
						'text'       => '#000000',
					),
					'elements' => array(
						'button' => array(
							'color' => array(
								'background' => array( 'ref' => 'styles.color.text' ),
								'text'       => array( 'ref' => 'styles.color.background' ),
							),
						),
					),
				),
			)
		);

		$expected = 'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }body{background-color: #ffffff;color: #000000;}.wp-element-button, .wp-block-button__link{background-color: #000000;color: #ffffff;}';
		$this->assertSame( $expected, $theme_json->get_stylesheet() );
	}

	/**
	 * Tests that get_property_value() static method returns an empty string
	 * if the path is invalid or the value is null.
	 *
	 * Also, tests that PHP 8.1 "passing null to non-nullable" deprecation notice
	 * is not thrown when passing the value to strncmp() in the method.
	 *
	 * The notice that we should not see:
	 * `Deprecated: strncmp(): Passing null to parameter #1 ($string1) of type string is deprecated`.
	 *
	 * @dataProvider data_get_property_value_should_return_string_for_invalid_paths_or_null_values
	 *
	 * @ticket 56620
	 *
	 * @covers WP_Theme_JSON::get_property_value
	 *
	 * @param array $styles An array with style definitions.
	 * @param array $path   Path to the desired properties.
	 *
	 */
	public function test_get_property_value_should_return_string_for_invalid_paths_or_null_values( $styles, $path ) {
		$reflection_class = new ReflectionClass( WP_Theme_JSON::class );

		$get_property_value_method = $reflection_class->getMethod( 'get_property_value' );
		$get_property_value_method->setAccessible( true );
		$result = $get_property_value_method->invoke( null, $styles, $path );

		$this->assertSame( '', $result );
	}

	/**
	 * Data provider for test_get_property_value_should_return_string_for_invalid_paths_or_null_values().
	 *
	 * @return array
	 */
	public function data_get_property_value_should_return_string_for_invalid_paths_or_null_values() {
		return array(
			'empty string' => array(
				'styles' => array(),
				'path'   => array( 'non_existent_path' ),
			),
			'null'         => array(
				'styles' => array( 'some_null_value' => null ),
				'path'   => array( 'some_null_value' ),
			),
		);
	}

	/**
	 * Testing that dynamic properties in theme.json that refer to other dynamic properties in a loop
	 * should be left untouched.
	 *
	 * @ticket 56467
	 * @expectedIncorrectUsage get_property_value
	 */
	public function test_get_property_value_loop() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version' => 2,
				'styles'  => array(
					'color'    => array(
						'background' => '#ffffff',
						'text'       => array( 'ref' => 'styles.elements.button.color.background' ),
					),
					'elements' => array(
						'button' => array(
							'color' => array(
								'background' => array( 'ref' => 'styles.color.text' ),
								'text'       => array( 'ref' => 'styles.color.background' ),
							),
						),
					),
				),
			)
		);

		$expected = 'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }body{background-color: #ffffff;}.wp-element-button, .wp-block-button__link{color: #ffffff;}';
		$this->assertSame( $expected, $theme_json->get_stylesheet() );
	}

	/**
	 * Testing that dynamic properties in theme.json that refer to other dynamic properties
	 * should be left unprocessed.
	 *
	 * @ticket 56467
	 * @expectedIncorrectUsage get_property_value
	 */
	public function test_get_property_value_recursion() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version' => 2,
				'styles'  => array(
					'color'    => array(
						'background' => '#ffffff',
						'text'       => array( 'ref' => 'styles.color.background' ),
					),
					'elements' => array(
						'button' => array(
							'color' => array(
								'background' => array( 'ref' => 'styles.color.text' ),
								'text'       => array( 'ref' => 'styles.color.background' ),
							),
						),
					),
				),
			)
		);

		$expected = 'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }body{background-color: #ffffff;color: #ffffff;}.wp-element-button, .wp-block-button__link{color: #ffffff;}';
		$this->assertSame( $expected, $theme_json->get_stylesheet() );
	}

	/**
	 * Testing that dynamic properties in theme.json that refer to themselves
	 * should be left unprocessed.
	 *
	 * @ticket 56467
	 * @expectedIncorrectUsage get_property_value
	 */
	public function test_get_property_value_self() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version' => 2,
				'styles'  => array(
					'color' => array(
						'background' => '#ffffff',
						'text'       => array( 'ref' => 'styles.color.text' ),
					),
				),
			)
		);

		$expected = 'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }body{background-color: #ffffff;}';
		$this->assertSame( $expected, $theme_json->get_stylesheet() );
	}

	/**
	 * @dataProvider data_get_layout_definitions
	 *
	 * @ticket 56467
	 *
	 * @param array $layout_definitions Layout definitions as stored in core theme.json.
	 */
	public function test_get_stylesheet_generates_layout_styles( $layout_definitions ) {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'layout'  => array(
						'definitions' => $layout_definitions,
					),
					'spacing' => array(
						'blockGap' => true,
					),
				),
				'styles'   => array(
					'spacing' => array(
						'blockGap' => '1em',
					),
				),
			),
			'default'
		);

		// Results also include root site blocks styles.
		$this->assertSame(
			'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }.wp-site-blocks > * { margin-block-start: 0; margin-block-end: 0; }.wp-site-blocks > * + * { margin-block-start: 1em; }body { --wp--style--block-gap: 1em; }body .is-layout-flow > *{margin-block-start: 0;margin-block-end: 0;}body .is-layout-flow > * + *{margin-block-start: 1em;margin-block-end: 0;}body .is-layout-flex{gap: 1em;}body .is-layout-flow > .alignleft{float: left;margin-inline-start: 0;margin-inline-end: 2em;}body .is-layout-flow > .alignright{float: right;margin-inline-start: 2em;margin-inline-end: 0;}body .is-layout-flow > .aligncenter{margin-left: auto !important;margin-right: auto !important;}body .is-layout-flex{display: flex;}body .is-layout-flex{flex-wrap: wrap;align-items: center;}',
			$theme_json->get_stylesheet( array( 'styles' ) )
		);
	}

	/**
	 * @dataProvider data_get_layout_definitions
	 *
	 * @ticket 56467
	 *
	 * @param array $layout_definitions Layout definitions as stored in core theme.json.
	 */
	public function test_get_stylesheet_generates_layout_styles_with_spacing_presets( $layout_definitions ) {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'layout'  => array(
						'definitions' => $layout_definitions,
					),
					'spacing' => array(
						'blockGap' => true,
					),
				),
				'styles'   => array(
					'spacing' => array(
						'blockGap' => 'var:preset|spacing|60',
					),
				),
			),
			'default'
		);

		// Results also include root site blocks styles.
		$this->assertSame(
			'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }.wp-site-blocks > * { margin-block-start: 0; margin-block-end: 0; }.wp-site-blocks > * + * { margin-block-start: var(--wp--preset--spacing--60); }body { --wp--style--block-gap: var(--wp--preset--spacing--60); }body .is-layout-flow > *{margin-block-start: 0;margin-block-end: 0;}body .is-layout-flow > * + *{margin-block-start: var(--wp--preset--spacing--60);margin-block-end: 0;}body .is-layout-flex{gap: var(--wp--preset--spacing--60);}body .is-layout-flow > .alignleft{float: left;margin-inline-start: 0;margin-inline-end: 2em;}body .is-layout-flow > .alignright{float: right;margin-inline-start: 2em;margin-inline-end: 0;}body .is-layout-flow > .aligncenter{margin-left: auto !important;margin-right: auto !important;}body .is-layout-flex{display: flex;}body .is-layout-flex{flex-wrap: wrap;align-items: center;}',
			$theme_json->get_stylesheet( array( 'styles' ) )
		);
	}

	/**
	 * @dataProvider data_get_layout_definitions
	 *
	 * @ticket 56467
	 *
	 * @param array $layout_definitions Layout definitions as stored in core theme.json.
	 */
	public function test_get_stylesheet_generates_fallback_gap_layout_styles( $layout_definitions ) {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'layout'  => array(
						'definitions' => $layout_definitions,
					),
					'spacing' => array(
						'blockGap' => null,
					),
				),
				'styles'   => array(
					'spacing' => array(
						'blockGap' => '1em',
					),
				),
			),
			'default'
		);
		$stylesheet = $theme_json->get_stylesheet( array( 'styles' ) );

		// Results also include root site blocks styles.
		$this->assertSame(
			'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }:where(.is-layout-flex){gap: 0.5em;}body .is-layout-flow > .alignleft{float: left;margin-inline-start: 0;margin-inline-end: 2em;}body .is-layout-flow > .alignright{float: right;margin-inline-start: 2em;margin-inline-end: 0;}body .is-layout-flow > .aligncenter{margin-left: auto !important;margin-right: auto !important;}body .is-layout-flex{display: flex;}body .is-layout-flex{flex-wrap: wrap;align-items: center;}',
			$stylesheet
		);
	}

	/**
	 * @dataProvider data_get_layout_definitions
	 *
	 * @ticket 56467
	 *
	 * @param array $layout_definitions Layout definitions as stored in core theme.json.
	 */
	public function test_get_stylesheet_generates_base_fallback_gap_layout_styles( $layout_definitions ) {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'layout'  => array(
						'definitions' => $layout_definitions,
					),
					'spacing' => array(
						'blockGap' => null,
					),
				),
			),
			'default'
		);
		$stylesheet = $theme_json->get_stylesheet( array( 'base-layout-styles' ) );

		// Note the `base-layout-styles` includes a fallback gap for the Columns block for backwards compatibility.
		$this->assertSame(
			':where(.is-layout-flex){gap: 0.5em;}body .is-layout-flow > .alignleft{float: left;margin-inline-start: 0;margin-inline-end: 2em;}body .is-layout-flow > .alignright{float: right;margin-inline-start: 2em;margin-inline-end: 0;}body .is-layout-flow > .aligncenter{margin-left: auto !important;margin-right: auto !important;}body .is-layout-flex{display: flex;}body .is-layout-flex{flex-wrap: wrap;align-items: center;}:where(.wp-block-columns.is-layout-flex){gap: 2em;}',
			$stylesheet
		);
	}

	/**
	 * @dataProvider data_get_layout_definitions
	 *
	 * @ticket 56467
	 *
	 * @param array $layout_definitions Layout definitions as stored in core theme.json.
	 */
	public function test_get_stylesheet_skips_layout_styles( $layout_definitions ) {
		add_theme_support( 'disable-layout-styles' );
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'layout'  => array(
						'definitions' => $layout_definitions,
					),
					'spacing' => array(
						'blockGap' => null,
					),
				),
			),
			'default'
		);
		$stylesheet = $theme_json->get_stylesheet( array( 'base-layout-styles' ) );
		remove_theme_support( 'disable-layout-styles' );

		// All Layout styles should be skipped.
		$this->assertSame(
			'',
			$stylesheet
		);
	}

	/**
	 * @dataProvider data_get_layout_definitions
	 *
	 * @ticket 56467
	 *
	 * @param array $layout_definitions Layout definitions as stored in core theme.json.
	 */
	public function test_get_stylesheet_generates_valid_block_gap_values_and_skips_null_or_false_values( $layout_definitions ) {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => WP_Theme_JSON::LATEST_SCHEMA,
				'settings' => array(
					'layout'  => array(
						'definitions' => $layout_definitions,
					),
					'spacing' => array(
						'blockGap' => true,
					),
				),
				'styles'   => array(
					'spacing' => array(
						'blockGap' => '1rem',
					),
					'blocks'  => array(
						'core/post-content' => array(
							'color' => array(
								'text' => 'gray', // This value should not render block layout styles.
							),
						),
						'core/social-links' => array(
							'spacing' => array(
								'blockGap' => '0', // This value should render block layout gap as zero.
							),
						),
						'core/buttons'      => array(
							'spacing' => array(
								'blockGap' => 0, // This value should render block layout gap as zero.
							),
						),
						'core/columns'      => array(
							'spacing' => array(
								'blockGap' => false, // This value should be ignored. The block will use the global layout value.
							),
						),
					),
				),
			),
			'default'
		);

		$this->assertSame(
			'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }.wp-site-blocks > * { margin-block-start: 0; margin-block-end: 0; }.wp-site-blocks > * + * { margin-block-start: 1rem; }body { --wp--style--block-gap: 1rem; }body .is-layout-flow > *{margin-block-start: 0;margin-block-end: 0;}body .is-layout-flow > * + *{margin-block-start: 1rem;margin-block-end: 0;}body .is-layout-flex{gap: 1rem;}body .is-layout-flow > .alignleft{float: left;margin-inline-start: 0;margin-inline-end: 2em;}body .is-layout-flow > .alignright{float: right;margin-inline-start: 2em;margin-inline-end: 0;}body .is-layout-flow > .aligncenter{margin-left: auto !important;margin-right: auto !important;}body .is-layout-flex{display: flex;}body .is-layout-flex{flex-wrap: wrap;align-items: center;}.wp-block-post-content{color: gray;}.wp-block-social-links.is-layout-flow > *{margin-block-start: 0;margin-block-end: 0;}.wp-block-social-links.is-layout-flow > * + *{margin-block-start: 0;margin-block-end: 0;}.wp-block-social-links.is-layout-flex{gap: 0;}.wp-block-buttons.is-layout-flow > *{margin-block-start: 0;margin-block-end: 0;}.wp-block-buttons.is-layout-flow > * + *{margin-block-start: 0;margin-block-end: 0;}.wp-block-buttons.is-layout-flex{gap: 0;}',
			$theme_json->get_stylesheet()
		);
	}

	/**
	 * Data provider for layout tests.
	 *
	 * @ticket 56467
	 *
	 * @return array
	 */
	public function data_get_layout_definitions() {
		return array(
			'layout definitions' => array(
				array(
					'default' => array(
						'name'          => 'default',
						'slug'          => 'flow',
						'className'     => 'is-layout-flow',
						'baseStyles'    => array(
							array(
								'selector' => ' > .alignleft',
								'rules'    => array(
									'float'               => 'left',
									'margin-inline-start' => '0',
									'margin-inline-end'   => '2em',
								),
							),
							array(
								'selector' => ' > .alignright',
								'rules'    => array(
									'float'               => 'right',
									'margin-inline-start' => '2em',
									'margin-inline-end'   => '0',
								),
							),
							array(
								'selector' => ' > .aligncenter',
								'rules'    => array(
									'margin-left'  => 'auto !important',
									'margin-right' => 'auto !important',
								),
							),
						),
						'spacingStyles' => array(
							array(
								'selector' => ' > *',
								'rules'    => array(
									'margin-block-start' => '0',
									'margin-block-end'   => '0',
								),
							),
							array(
								'selector' => ' > * + *',
								'rules'    => array(
									'margin-block-start' => null,
									'margin-block-end'   => '0',
								),
							),
						),
					),
					'flex'    => array(
						'name'          => 'flex',
						'slug'          => 'flex',
						'className'     => 'is-layout-flex',
						'displayMode'   => 'flex',
						'baseStyles'    => array(
							array(
								'selector' => '',
								'rules'    => array(
									'flex-wrap'   => 'wrap',
									'align-items' => 'center',
								),
							),
						),
						'spacingStyles' => array(
							array(
								'selector' => '',
								'rules'    => array(
									'gap' => null,
								),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * @ticket 56467
	 */
	function test_get_styles_for_block_with_padding_aware_alignments() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => 2,
				'styles'   => array(
					'spacing' => array(
						'padding' => array(
							'top'    => '10px',
							'right'  => '12px',
							'bottom' => '10px',
							'left'   => '12px',
						),
					),
				),
				'settings' => array(
					'useRootPaddingAwareAlignments' => true,
				),
			)
		);

		$metadata = array(
			'path'     => array( 'styles' ),
			'selector' => 'body',
		);

		$expected    = 'body { margin: 0; }.wp-site-blocks { padding-top: var(--wp--style--root--padding-top); padding-bottom: var(--wp--style--root--padding-bottom); }.has-global-padding { padding-right: var(--wp--style--root--padding-right); padding-left: var(--wp--style--root--padding-left); }.has-global-padding :where(.has-global-padding) { padding-right: 0; padding-left: 0; }.has-global-padding > .alignfull { margin-right: calc(var(--wp--style--root--padding-right) * -1); margin-left: calc(var(--wp--style--root--padding-left) * -1); }.has-global-padding :where(.has-global-padding) > .alignfull { margin-right: 0; margin-left: 0; }.has-global-padding > .alignfull:where(:not(.has-global-padding)) > :where([class*="wp-block-"]:not(.alignfull):not([class*="__"]),p,h1,h2,h3,h4,h5,h6,ul,ol) { padding-right: var(--wp--style--root--padding-right); padding-left: var(--wp--style--root--padding-left); }.has-global-padding :where(.has-global-padding) > .alignfull:where(:not(.has-global-padding)) > :where([class*="wp-block-"]:not(.alignfull):not([class*="__"]),p,h1,h2,h3,h4,h5,h6,ul,ol) { padding-right: 0; padding-left: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }body{--wp--style--root--padding-top: 10px;--wp--style--root--padding-right: 12px;--wp--style--root--padding-bottom: 10px;--wp--style--root--padding-left: 12px;}';
		$root_rules  = $theme_json->get_root_layout_rules( WP_Theme_JSON::ROOT_BLOCK_SELECTOR, $metadata );
		$style_rules = $theme_json->get_styles_for_block( $metadata );
		$this->assertSame( $expected, $root_rules . $style_rules );
	}

	/**
	 * @ticket 56467
	 */
	function test_get_styles_for_block_without_padding_aware_alignments() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version' => 2,
				'styles'  => array(
					'spacing' => array(
						'padding' => array(
							'top'    => '10px',
							'right'  => '12px',
							'bottom' => '10px',
							'left'   => '12px',
						),
					),
				),
			)
		);

		$metadata = array(
			'path'     => array( 'styles' ),
			'selector' => 'body',
		);

		$expected    = 'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }body{padding-top: 10px;padding-right: 12px;padding-bottom: 10px;padding-left: 12px;}';
		$root_rules  = $theme_json->get_root_layout_rules( WP_Theme_JSON::ROOT_BLOCK_SELECTOR, $metadata );
		$style_rules = $theme_json->get_styles_for_block( $metadata );
		$this->assertSame( $expected, $root_rules . $style_rules );
	}

	/**
	 * @ticket 56467
	 */
	function test_get_styles_for_block_with_content_width() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => 2,
				'settings' => array(
					'layout' => array(
						'contentSize' => '800px',
						'wideSize'    => '1000px',
					),
				),
			)
		);

		$metadata = array(
			'path'     => array( 'settings' ),
			'selector' => 'body',
		);

		$expected    = 'body { margin: 0;--wp--style--global--content-size: 800px;--wp--style--global--wide-size: 1000px; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }';
		$root_rules  = $theme_json->get_root_layout_rules( WP_Theme_JSON::ROOT_BLOCK_SELECTOR, $metadata );
		$style_rules = $theme_json->get_styles_for_block( $metadata );
		$this->assertSame( $expected, $root_rules . $style_rules );
	}

	/**
	 * @ticket 56611
	 */
	function test_get_styles_with_appearance_tools() {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => 2,
				'settings' => array(
					'appearanceTools' => true,
				),
			)
		);

		$metadata = array(
			'path'     => array( 'settings' ),
			'selector' => 'body',
		);

		$expected   = 'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }.wp-site-blocks > * { margin-block-start: 0; margin-block-end: 0; }.wp-site-blocks > * + * { margin-block-start: ; }body { --wp--style--block-gap: ; }';
		$root_rules = $theme_json->get_root_layout_rules( WP_Theme_JSON::ROOT_BLOCK_SELECTOR, $metadata );
		$this->assertSame( $expected, $root_rules );
	}

	/**
	 * Tests generating the spacing presets array based on the spacing scale provided.
	 *
	 * @ticket 56467
	 *
	 * @dataProvider data_generate_spacing_scale_fixtures
	 *
	 * @param array $spacing_scale   Example spacing scale definitions from the data provider.
	 * @param array $expected_output Expected output from data provider.
	 */
	function test_should_set_spacing_sizes( $spacing_scale, $expected_output ) {
		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => 2,
				'settings' => array(
					'spacing' => array(
						'spacingScale' => $spacing_scale,
					),
				),
			)
		);

		$theme_json->set_spacing_sizes();
		$this->assertSame( $expected_output, _wp_array_get( $theme_json->get_raw_data(), array( 'settings', 'spacing', 'spacingSizes', 'default' ) ) );
	}

	/**
	 * Data provider for spacing scale tests.
	 *
	 * @ticket 56467
	 *
	 * @return array
	 */
	function data_generate_spacing_scale_fixtures() {
		return array(
			'only one value when single step in spacing scale' => array(
				'spacing_scale'   => array(
					'operator'   => '+',
					'increment'  => 1.5,
					'steps'      => 1,
					'mediumStep' => 4,
					'unit'       => 'rem',
				),
				'expected_output' => array(
					array(
						'name' => '1',
						'slug' => '50',
						'size' => '4rem',
					),
				),
			),
			'one step above medium when two steps in spacing scale' => array(
				'spacing_scale'   => array(
					'operator'   => '+',
					'increment'  => 1.5,
					'steps'      => 2,
					'mediumStep' => 4,
					'unit'       => 'rem',
				),
				'expected_output' => array(
					array(
						'name' => '1',
						'slug' => '50',
						'size' => '4rem',
					),
					array(
						'name' => '2',
						'slug' => '60',
						'size' => '5.5rem',
					),
				),
			),
			'one step above medium and one below when three steps in spacing scale' => array(
				'spacing_scale'   => array(
					'operator'   => '+',
					'increment'  => 1.5,
					'steps'      => 3,
					'mediumStep' => 4,
					'unit'       => 'rem',
				),
				'expected_output' => array(
					array(
						'name' => '1',
						'slug' => '40',
						'size' => '2.5rem',
					),
					array(
						'name' => '2',
						'slug' => '50',
						'size' => '4rem',
					),
					array(
						'name' => '3',
						'slug' => '60',
						'size' => '5.5rem',
					),
				),
			),
			'extra step added above medium when an even number of steps > 2 specified' => array(
				'spacing_scale'   => array(
					'operator'   => '+',
					'increment'  => 1.5,
					'steps'      => 4,
					'mediumStep' => 4,
					'unit'       => 'rem',
				),
				'expected_output' => array(
					array(
						'name' => '1',
						'slug' => '40',
						'size' => '2.5rem',
					),
					array(
						'name' => '2',
						'slug' => '50',
						'size' => '4rem',
					),
					array(
						'name' => '3',
						'slug' => '60',
						'size' => '5.5rem',
					),
					array(
						'name' => '4',
						'slug' => '70',
						'size' => '7rem',
					),
				),
			),
			'extra steps above medium if bottom end will go below zero' => array(
				'spacing_scale'   => array(
					'operator'   => '+',
					'increment'  => 2.5,
					'steps'      => 5,
					'mediumStep' => 5,
					'unit'       => 'rem',
				),
				'expected_output' => array(
					array(
						'name' => '1',
						'slug' => '40',
						'size' => '2.5rem',
					),
					array(
						'name' => '2',
						'slug' => '50',
						'size' => '5rem',
					),
					array(
						'name' => '3',
						'slug' => '60',
						'size' => '7.5rem',
					),
					array(
						'name' => '4',
						'slug' => '70',
						'size' => '10rem',
					),
					array(
						'name' => '5',
						'slug' => '80',
						'size' => '12.5rem',
					),
				),
			),
			'multiplier correctly calculated above and below medium' => array(
				'spacing_scale'   => array(
					'operator'   => '*',
					'increment'  => 1.5,
					'steps'      => 5,
					'mediumStep' => 1.5,
					'unit'       => 'rem',
				),
				'expected_output' => array(
					array(
						'name' => '1',
						'slug' => '30',
						'size' => '0.67rem',
					),
					array(
						'name' => '2',
						'slug' => '40',
						'size' => '1rem',
					),
					array(
						'name' => '3',
						'slug' => '50',
						'size' => '1.5rem',
					),
					array(
						'name' => '4',
						'slug' => '60',
						'size' => '2.25rem',
					),
					array(
						'name' => '5',
						'slug' => '70',
						'size' => '3.38rem',
					),
				),
			),
			'increment < 1 combined showing * operator acting as divisor above and below medium' => array(
				'spacing_scale'   => array(
					'operator'   => '*',
					'increment'  => 0.25,
					'steps'      => 5,
					'mediumStep' => 1.5,
					'unit'       => 'rem',
				),
				'expected_output' => array(
					array(
						'name' => '1',
						'slug' => '30',
						'size' => '0.09rem',
					),
					array(
						'name' => '2',
						'slug' => '40',
						'size' => '0.38rem',
					),
					array(
						'name' => '3',
						'slug' => '50',
						'size' => '1.5rem',
					),
					array(
						'name' => '4',
						'slug' => '60',
						'size' => '6rem',
					),
					array(
						'name' => '5',
						'slug' => '70',
						'size' => '24rem',
					),
				),
			),
			't-shirt sizing used if more than 7 steps in scale' => array(
				'spacing_scale'   => array(
					'operator'   => '*',
					'increment'  => 1.5,
					'steps'      => 8,
					'mediumStep' => 1.5,
					'unit'       => 'rem',
				),
				'expected_output' => array(
					array(
						'name' => '2X-Small',
						'slug' => '20',
						'size' => '0.44rem',
					),
					array(
						'name' => 'X-Small',
						'slug' => '30',
						'size' => '0.67rem',
					),
					array(
						'name' => 'Small',
						'slug' => '40',
						'size' => '1rem',
					),
					array(
						'name' => 'Medium',
						'slug' => '50',
						'size' => '1.5rem',
					),
					array(
						'name' => 'Large',
						'slug' => '60',
						'size' => '2.25rem',
					),
					array(
						'name' => 'X-Large',
						'slug' => '70',
						'size' => '3.38rem',
					),
					array(
						'name' => '2X-Large',
						'slug' => '80',
						'size' => '5.06rem',
					),
					array(
						'name' => '3X-Large',
						'slug' => '90',
						'size' => '7.59rem',
					),
				),
			),
		);
	}

	/**
	 * Tests generating the spacing presets array based on the spacing scale provided.
	 *
	 * @ticket 56467
	 *
	 * @dataProvider data_set_spacing_sizes_when_invalid
	 *
	 * @param array $spacing_scale   Example spacing scale definitions from the data provider.
	 * @param array $expected_output Expected output from data provider.
	 */
	public function test_set_spacing_sizes_should_detect_invalid_spacing_scale( $spacing_scale, $expected_output ) {
		$this->expectNotice();
		$this->expectNoticeMessage( 'Some of the theme.json settings.spacing.spacingScale values are invalid' );

		$theme_json = new WP_Theme_JSON(
			array(
				'version'  => 2,
				'settings' => array(
					'spacing' => array(
						'spacingScale' => $spacing_scale,
					),
				),
			)
		);

		$theme_json->set_spacing_sizes();
		$this->assertSame( $expected_output, _wp_array_get( $theme_json->get_raw_data(), array( 'settings', 'spacing', 'spacingSizes', 'default' ) ) );
	}

	/**
	 * Data provider for spacing scale tests.
	 *
	 * @ticket 56467
	 *
	 * @return array
	 */
	function data_set_spacing_sizes_when_invalid() {
		return array(
			'missing operator value'  => array(
				'spacing_scale'   => array(
					'operator'   => '',
					'increment'  => 1.5,
					'steps'      => 1,
					'mediumStep' => 4,
					'unit'       => 'rem',
				),
				'expected_output' => null,
			),
			'non numeric increment'   => array(
				'spacing_scale'   => array(
					'operator'   => '+',
					'increment'  => 'add two to previous value',
					'steps'      => 1,
					'mediumStep' => 4,
					'unit'       => 'rem',
				),
				'expected_output' => null,
			),
			'non numeric steps'       => array(
				'spacing_scale'   => array(
					'operator'   => '+',
					'increment'  => 1.5,
					'steps'      => 'spiral staircase preferred',
					'mediumStep' => 4,
					'unit'       => 'rem',
				),
				'expected_output' => null,
			),
			'non numeric medium step' => array(
				'spacing_scale'   => array(
					'operator'   => '+',
					'increment'  => 1.5,
					'steps'      => 5,
					'mediumStep' => 'That which is just right',
					'unit'       => 'rem',
				),
				'expected_output' => null,
			),
			'missing unit value'      => array(
				'spacing_scale'   => array(
					'operator'   => '+',
					'increment'  => 1.5,
					'steps'      => 5,
					'mediumStep' => 4,
				),
				'expected_output' => null,
			),
		);
	}

	/**
	 * Tests the core separator block outbut based on various provided settings.
	 *
	 * @ticket 56903
	 *
	 * @dataProvider data_update_separator_declarations
	 *
	 * @param array $separator_block_settings Example separator block settings from the data provider.
	 * @param array $expected_output          Expected output from data provider.
	 */
	public function test_update_separator_declarations( $separator_block_settings, $expected_output ) {
		// If only background is defined, test that includes border-color to the style so it is applied on the front end.
		$theme_json = new WP_Theme_JSON(
			array(
				'version' => WP_Theme_JSON::LATEST_SCHEMA,
				'styles'  => array(
					'blocks' => array(
						'core/separator' => $separator_block_settings,
					),
				),
			),
			'default'
		);

		$stylesheet = $theme_json->get_stylesheet( array( 'styles' ) );

		$this->assertSame( $expected_output, $stylesheet );
	}

	/**
	 * Data provider for separator declaration tests.
	 *
	 * @return array
	 */
	function data_update_separator_declarations() {
		return array(
			// If only background is defined, test that includes border-color to the style so it is applied on the front end.
			'only background'                      => array(
				array(
					'color' => array(
						'background' => 'blue',
					),
				),
				'expected_output' => 'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }.wp-block-separator{background-color: blue;color: blue;}',
			),
			// If background and text are defined, do not include border-color, as text color is enough.
			'background and text, no border-color' => array(
				array(
					'color' => array(
						'background' => 'blue',
						'text'       => 'red',
					),
				),
				'expected_output' => 'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }.wp-block-separator{background-color: blue;color: red;}',
			),
			// If only text is defined, do not include border-color, as by itself is enough.
			'only text'                            => array(
				array(
					'color' => array(
						'text' => 'red',
					),
				),
				'expected_output' => 'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }.wp-block-separator{color: red;}',
			),
			// If background, text, and border-color are defined, include everything, CSS specifity will decide which to apply.
			'background, text, and border-color'   => array(
				array(
					'color'  => array(
						'background' => 'blue',
						'text'       => 'red',
					),
					'border' => array(
						'color' => 'pink',
					),
				),
				'expected_output' => 'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }.wp-block-separator{background-color: blue;border-color: pink;color: red;}',
			),
			// If background and border color are defined, include everything, CSS specifity will decide which to apply.
			'background, text, and border-color'   => array(
				array(
					'color'  => array(
						'background' => 'blue',
					),
					'border' => array(
						'color' => 'pink',
					),
				),
				'expected_output' => 'body { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }.wp-block-separator{background-color: blue;border-color: pink;}',
			),
		);
	}
}
