<?php

/**
 * Test the block WP_Duotone class.
 *
 * @group block-supports
 *
 * @coversDefaultClass WP_Duotone
 */

class Tests_Block_Supports_Duotone extends WP_UnitTestCase {
	/**
	 * Tests whether the duotone preset class is added to the block.
	 *
	 * @ticket 58555
	 *
	 * @covers ::render_duotone_support
	 */
	public function test_render_duotone_support_preset() {
		$block         = array(
			'blockName' => 'core/image',
			'attrs'     => array( 'style' => array( 'color' => array( 'duotone' => 'var:preset|duotone|blue-orange' ) ) ),
		);
		$wp_block      = new WP_Block( $block );
		$block_content = '<figure class="wp-block-image size-full"><img src="/my-image.jpg" /></figure>';
		$expected      = '<figure class="wp-block-image size-full wp-duotone-blue-orange"><img src="/my-image.jpg" /></figure>';
		$this->assertSame( $expected, WP_Duotone::render_duotone_support( $block_content, $block, $wp_block ) );
	}

	/**
	 * Tests whether the duotone unset class is added to the block.
	 *
	 * @ticket 58555
	 *
	 * @covers ::render_duotone_support
	 */
	public function test_render_duotone_support_css() {
		$block         = array(
			'blockName' => 'core/image',
			'attrs'     => array( 'style' => array( 'color' => array( 'duotone' => 'unset' ) ) ),
		);
		$wp_block      = new WP_Block( $block );
		$block_content = '<figure class="wp-block-image size-full"><img src="/my-image.jpg" /></figure>';
		$expected      = '/<figure class="wp-block-image size-full wp-duotone-unset-\d+"><img src="\\/my-image.jpg" \\/><\\/figure>/';
		$this->assertMatchesRegularExpression( $expected, WP_Duotone::render_duotone_support( $block_content, $block, $wp_block ) );
	}

	/**
	 * Tests whether the duotone custom class is added to the block.
	 *
	 * @covers ::render_duotone_support
	 */
	public function test_render_duotone_support_custom() {
		$block         = array(
			'blockName' => 'core/image',
			'attrs'     => array( 'style' => array( 'color' => array( 'duotone' => array( '#FFFFFF', '#000000' ) ) ) ),
		);
		$wp_block      = new WP_Block( $block );
		$block_content = '<figure class="wp-block-image size-full"><img src="/my-image.jpg" /></figure>';
		$expected      = '/<figure class="wp-block-image size-full wp-duotone-ffffff-000000-\d+"><img src="\\/my-image.jpg" \\/><\\/figure>/';
		$this->assertMatchesRegularExpression( $expected, WP_Duotone::render_duotone_support( $block_content, $block, $wp_block ) );
	}

	/**
	 * @ticket 65576
	 *
	 * @covers ::restore_image_outer_container
	 */
	public function test_restore_image_outer_container_moves_duotone_class_to_wrapper_in_classic_theme() {
		switch_theme( 'default' );

		$block_content = '<div class="wp-block-image"><figure class="alignright wp-duotone-blue-orange size-full"><img src="/my-image.jpg"></figure></div>';
		$expected      = '<div class="wp-block-image wp-duotone-blue-orange"><figure class="alignright size-full"><img src="/my-image.jpg"></figure></div>';

		$this->assertEqualHTML( $expected, WP_Duotone::restore_image_outer_container( $block_content ) );
	}

	/**
	 * Tests whether the slug is extracted from the attribute.
	 *
	 * @dataProvider data_get_slug_from_attribute
	 * @covers ::get_slug_from_attribute
	 */
	public function test_get_slug_from_attribute( $data_attr, $expected ) {

		$reflection = new ReflectionMethod( 'WP_Duotone', 'get_slug_from_attribute' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		$this->assertSame( $expected, $reflection->invoke( null, $data_attr ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public function data_get_slug_from_attribute() {
		return array(
			'pipe-slug'                       => array( 'var:preset|duotone|blue-orange', 'blue-orange' ),
			'css-var'                         => array( 'var(--wp--preset--duotone--blue-orange)', 'blue-orange' ),
			'css-var-invalid-slug-chars'      => array( 'var(--wp--preset--duotone--.)', '.' ),
			'css-var-missing-end-parenthesis' => array( 'var(--wp--preset--duotone--blue-orange', '' ),
			'invalid'                         => array( 'not a valid attribute', '' ),
			'css-var-no-value'                => array( 'var(--wp--preset--duotone--)', '' ),
			'pipe-slug-no-value'              => array( 'var:preset|duotone|', '' ),
			'css-var-spaces'                  => array( 'var(--wp--preset--duotone--    ', '' ),
			'pipe-slug-spaces'                => array( 'var:preset|duotone|  ', '' ),
			'array-of-colors'                 => array( array( '#000000', '#ffffff' ), '' ),
			'empty-array'                     => array( array(), '' ),
		);
	}

	/**
	 * Tests whether the CSS declarations are generated even if the block content is
	 * empty. This is needed to make the CSS output stable across paginations for
	 * features like the enhanced pagination of the Query block.
	 *
	 * @ticket 59694
	 *
	 * @covers ::render_duotone_support
	 */
	public function test_css_declarations_are_generated_even_with_empty_block_content() {
		$block    = array(
			'blockName' => 'core/image',
			'attrs'     => array( 'style' => array( 'color' => array( 'duotone' => 'var:preset|duotone|blue-orange' ) ) ),
		);
		$wp_block = new WP_Block( $block );

		/*
		 * Handling to access the static WP_Duotone::$block_css_declarations property.
		 *
		 * Why is an instance needed?
		 * WP_Duotone is a static class by design, meaning it only contains static properties and methods.
		 * In production, it should not be instantiated. However, as of PHP 8.3, ReflectionProperty::setValue()
		 * needs an object.
		 */
		$wp_duotone                      = new WP_Duotone();
		$block_css_declarations_property = new ReflectionProperty( 'WP_Duotone', 'block_css_declarations' );
		if ( PHP_VERSION_ID < 80100 ) {
			$block_css_declarations_property->setAccessible( true );
		}
		$previous_value = $block_css_declarations_property->getValue();
		$block_css_declarations_property->setValue( $wp_duotone, array() );

		WP_Duotone::render_duotone_support( '', $block, $wp_block );
		$actual = $block_css_declarations_property->getValue();

		// Reset the property.
		$block_css_declarations_property->setValue( $wp_duotone, $previous_value );
		if ( PHP_VERSION_ID < 80100 ) {
			$block_css_declarations_property->setAccessible( false );
		}

		$this->assertNotEmpty( $actual );
	}

	/**
	 * @dataProvider data_is_preset
	 */
	public function test_is_preset( $data_attr, $expected ) {
		$reflection = new ReflectionMethod( 'WP_Duotone', 'is_preset' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		$this->assertSame( $expected, $reflection->invoke( null, $data_attr ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public function data_is_preset() {
		return array(
			'pipe-slug'                       => array( 'var:preset|duotone|blue-orange', true ),
			'css-var'                         => array( 'var(--wp--preset--duotone--blue-orange)', true ),
			'css-var-invalid-slug-chars'      => array( 'var(--wp--preset--duotone--.)', false ),
			'css-var-missing-end-parenthesis' => array( 'var(--wp--preset--duotone--blue-orange', false ),
			'invalid'                         => array( 'not a valid attribute', false ),
			'array-of-colors'                 => array( array( '#000000', '#ffffff' ), false ),
			'empty-array'                     => array( array(), false ),
		);
	}

	/**
	 * @dataProvider data_colord_parse_hue
	 * @ticket 59496
	 */
	public function test_colord_parse_hue( $value, $unit, $expected ) {
		$reflection = new ReflectionMethod( 'WP_Duotone', 'colord_parse_hue' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		$this->assertSame( $expected, $reflection->invoke( null, $value, $unit ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public function data_colord_parse_hue() {
		return array(
			'deg-angle-unit'                => array( 120, 'deg', 120.0 ),
			'grad-angle-unit'               => array( 120, 'grad', 108.0 ),
			'turn-angle-unit'               => array( 120, 'turn', 43200.0 ),
			'rad-angle-unit'                => array( 120, 'rad', 6875.493541569878 ),
			'empty-angle-unit'              => array( 120, '', 120.0 ),
			'invalid-angle-unit'            => array( 120, 'invalid', 120.0 ),
			'negative-value-deg-angle-unit' => array( -120, 'deg', -120.0 ),
		);
	}


	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_get_custom_filter_id() {
		return array(
			'hex'               => array( array( '#000000', '#FFFFFF' ), 'wp-duotone-custom-000000-ffffff' ),
			'short-hex'         => array( array( '#000', '#fff' ), 'wp-duotone-custom-000000-ffffff' ),
			'rgb-and-hsl'       => array( array( 'rgb(255, 0, 0)', 'hsl(240, 100%, 50%)' ), 'wp-duotone-custom-ff0000-0000ff' ),
			'alpha'             => array( array( '#000000', 'rgba(255, 255, 255, 0.5)' ), 'wp-duotone-custom-000000-ffffff80' ),
			'three-colors'      => array( array( '#000', '#f00', '#fff' ), 'wp-duotone-custom-000000-ff0000-ffffff' ),
			'named-color'       => array( array( 'red', '#fff' ), null ),
			'markup'            => array( array( '#000', '<script>' ), null ),
			'nested-array'      => array( array( array( '#000' ), '#fff' ), null ),
			'single-color'      => array( array( '#000' ), null ),
			'associative-array' => array(
				array(
					'dark'  => '#000',
					'light' => '#fff',
				),
				null,
			),
			'string'            => array( 'var:preset|duotone|blue-orange', null ),
			'empty-array'       => array( array(), null ),
		);
	}

	/**
	 * @ticket XXXXX
	 *
	 * @dataProvider data_get_custom_filter_id
	 *
	 * @covers ::get_custom_filter_id
	 */
	public function test_get_custom_filter_id( $colors, $expected ) {
		$this->assertSame( $expected, WP_Duotone::get_custom_filter_id( $colors ) );
	}

	/**
	 * Renders a block with the given user global styles, then resets the cached data.
	 *
	 * @param array $styles The user global styles.
	 * @param array $block  The parsed block.
	 * @return array The rendered block HTML, the footer assets, the used preset filter IDs and the block CSS rules.
	 */
	private function render_with_user_global_styles( $styles, $block ) {
		$set_user_styles = static function ( $theme_json ) use ( $styles ) {
			return $theme_json->update_with(
				array(
					'version' => WP_Theme_JSON::LATEST_SCHEMA,
					'styles'  => $styles,
				)
			);
		};
		$wp_duotone      = new WP_Duotone();
		$properties      = array();
		foreach ( array( 'global_styles_block_names', 'global_styles_variation_filters', 'used_svg_filter_data', 'used_global_styles_presets', 'block_css_declarations' ) as $name ) {
			$properties[ $name ] = new ReflectionProperty( 'WP_Duotone', $name );
			if ( PHP_VERSION_ID < 80100 ) {
				$properties[ $name ]->setAccessible( true );
			}
		}
		$reset = static function () use ( $properties, $wp_duotone ) {
			foreach ( $properties as $name => $property ) {
				$property->setValue( $wp_duotone, str_starts_with( $name, 'global_' ) ? null : array() );
			}
		};

		add_filter( 'wp_theme_json_data_user', $set_user_styles );
		WP_Theme_JSON_Resolver::clean_cached_data();
		$reset();

		$block_content = '<figure class="wp-block-image size-full"><img src="/my-image.jpg" /></figure>';
		$html          = WP_Duotone::render_duotone_support( $block_content, $block, new WP_Block( $block ) );
		$used_presets  = array_keys( $properties['used_global_styles_presets']->getValue() );
		$css_rules     = $properties['block_css_declarations']->getValue();

		ob_start();
		WP_Duotone::output_footer_assets();
		$footer = ob_get_clean();

		remove_filter( 'wp_theme_json_data_user', $set_user_styles );
		WP_Theme_JSON_Resolver::clean_cached_data();
		$reset();

		return array( $html, $footer, $used_presets, $css_rules );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_global_styles_duotone() {
		return array(
			'custom colors' => array( array( '#000000', '#ff0000' ), 'wp-duotone-custom-000000-ff0000', array() ),
			'preset'        => array( 'var:preset|duotone|blue-orange', 'wp-duotone-blue-orange', array( 'wp-duotone-blue-orange' ) ),
		);
	}

	/**
	 * The theme.json stylesheet applies a block type's duotone, so a block
	 * style variation can follow it at the same specificity. Only the SVG and
	 * the preset custom property come from here.
	 *
	 * @ticket XXXXX
	 *
	 * @dataProvider data_global_styles_duotone
	 *
	 * @covers ::render_duotone_support
	 */
	public function test_render_duotone_support_global_styles_adds_no_class_or_block_css( $duotone, $filter_id, $expected_presets ) {
		list( $html, $footer, $used_presets, $css_rules ) = $this->render_with_user_global_styles(
			array(
				'blocks' => array(
					'core/image' => array(
						'filter' => array( 'duotone' => $duotone ),
					),
				),
			),
			array(
				'blockName' => 'core/image',
				'attrs'     => array(),
			)
		);

		$this->assertSame( '<figure class="wp-block-image size-full"><img src="/my-image.jpg" /></figure>', $html );
		$this->assertSame( array(), $css_rules );
		$this->assertStringContainsString( '<filter id="' . $filter_id . '">', $footer );
		$this->assertSame( $expected_presets, $used_presets );
	}

	/**
	 * @ticket XXXXX
	 *
	 * @covers ::render_duotone_support
	 */
	public function test_render_duotone_support_instance_keeps_its_class_and_block_css_over_global_styles() {
		list( $html, , , $css_rules ) = $this->render_with_user_global_styles(
			array(
				'blocks' => array(
					'core/image' => array(
						'filter' => array( 'duotone' => 'var:preset|duotone|blue-orange' ),
					),
				),
			),
			array(
				'blockName' => 'core/image',
				'attrs'     => array( 'style' => array( 'color' => array( 'duotone' => array( '#000000', '#ff0000' ) ) ) ),
			)
		);

		$this->assertMatchesRegularExpression( '/class="wp-block-image size-full (wp-duotone-000000-ff0000-\d+)"/', $html );
		$this->assertCount( 1, $css_rules );
		$this->assertMatchesRegularExpression( '/^\.wp-duotone-000000-ff0000-\d+\.wp-block-image img/', $css_rules[0]['selector'] );
	}

	/**
	 * @ticket XXXXX
	 *
	 * @covers ::render_duotone_support
	 */
	public function test_render_duotone_support_global_styles_variations() {
		register_block_style(
			'core/image',
			array(
				'name'  => 'duotone-preset',
				'label' => 'Duotone preset',
			)
		);
		register_block_style(
			'core/group',
			array(
				'name'  => 'duotone-section',
				'label' => 'Duotone section',
			)
		);

		list( $html, $footer, $used_presets ) = $this->render_with_user_global_styles(
			array(
				'blocks' => array(
					'core/image' => array(
						'variations' => array(
							'rounded'        => array(
								'filter' => array( 'duotone' => array( '#000000', '#ff0000' ) ),
							),
							'duotone-preset' => array(
								'filter' => array( 'duotone' => 'var:preset|duotone|midnight' ),
							),
						),
					),
					'core/cover' => array(
						'variations' => array(
							'rounded' => array(
								'filter' => array( 'duotone' => array( '#0000ff', '#ffffff' ) ),
							),
						),
					),
					'core/group' => array(
						'variations' => array(
							'duotone-section' => array(
								'blocks' => array(
									'core/image' => array(
										'filter' => array( 'duotone' => 'var:preset|duotone|blue-orange' ),
									),
								),
							),
						),
					),
				),
			),
			array(
				'blockName' => 'core/image',
				'attrs'     => array( 'className' => 'is-style-rounded' ),
			)
		);

		unregister_block_style( 'core/image', 'duotone-preset' );
		unregister_block_style( 'core/group', 'duotone-section' );

		// The variation CSS comes from theme.json, so the block gets no class.
		$this->assertSame( '<figure class="wp-block-image size-full"><img src="/my-image.jpg" /></figure>', $html );
		$this->assertStringContainsString( '<filter id="wp-duotone-custom-000000-ff0000">', $footer );
		$this->assertStringContainsString( '<filter id="wp-duotone-midnight">', $footer );
		$this->assertStringContainsString( '<filter id="wp-duotone-blue-orange">', $footer );
		$this->assertSame( array( 'wp-duotone-midnight', 'wp-duotone-blue-orange' ), $used_presets );
		$this->assertStringNotContainsString( 'wp-duotone-custom-0000ff-ffffff', $footer, 'A variation filter for another block type is not output.' );
	}
}
