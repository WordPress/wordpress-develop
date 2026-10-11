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
	 * Tests that a preset whose slug is not kebab-case is rendered with a matching
	 * filter ID, CSS custom property, and block CSS declaration.
	 *
	 * @ticket 66285
	 *
	 * @covers ::render_duotone_support
	 * @covers ::get_global_styles_presets
	 *
	 * @dataProvider data_render_duotone_support_preset_with_non_kebab_case_slug
	 *
	 * @param string $preset_slug  The slug of the preset as registered in theme.json.
	 * @param string $duotone_attr The duotone attribute of the block.
	 * @param string $kebab_slug   The expected kebab-cased slug.
	 */
	public function test_render_duotone_support_preset_with_non_kebab_case_slug( $preset_slug, $duotone_attr, $kebab_slug ) {
		add_filter(
			'wp_theme_json_data_theme',
			static function ( $theme_json ) use ( $preset_slug ) {
				return $theme_json->update_with(
					array(
						'version'  => WP_Theme_JSON::LATEST_SCHEMA,
						'settings' => array(
							'color' => array(
								'duotone' => array(
									array(
										'slug'   => $preset_slug,
										'name'   => 'Test preset',
										'colors' => array( '#000000', '#ffffff' ),
									),
								),
							),
						),
					)
				);
			}
		);

		$this->set_duotone_static_property( 'global_styles_presets', null );
		$this->set_duotone_static_property( 'used_global_styles_presets', array() );
		$this->set_duotone_static_property( 'block_css_declarations', array() );
		wp_clean_theme_json_cache();

		$block         = array(
			'blockName' => 'core/image',
			'attrs'     => array( 'style' => array( 'color' => array( 'duotone' => $duotone_attr ) ) ),
		);
		$wp_block      = new WP_Block( $block );
		$block_content = '<figure class="wp-block-image size-full"><img src="/my-image.jpg" /></figure>';
		$actual        = WP_Duotone::render_duotone_support( $block_content, $block, $wp_block );

		$used_presets_property = new ReflectionProperty( 'WP_Duotone', 'used_global_styles_presets' );
		$declarations_property = new ReflectionProperty( 'WP_Duotone', 'block_css_declarations' );
		$presets_css_method    = new ReflectionMethod( 'WP_Duotone', 'get_global_styles_presets' );
		if ( PHP_VERSION_ID < 80100 ) {
			$used_presets_property->setAccessible( true );
			$declarations_property->setAccessible( true );
			$presets_css_method->setAccessible( true );
		}

		$used_presets = $used_presets_property->getValue();
		$declarations = $declarations_property->getValue();
		$presets_css  = $presets_css_method->invoke( null, $used_presets );

		// Reset the static properties so that other tests are not affected.
		$this->set_duotone_static_property( 'global_styles_presets', null );
		$this->set_duotone_static_property( 'used_global_styles_presets', array() );
		$this->set_duotone_static_property( 'block_css_declarations', array() );
		wp_clean_theme_json_cache();

		$this->assertSame(
			'<figure class="wp-block-image size-full wp-duotone-' . $kebab_slug . '"><img src="/my-image.jpg" /></figure>',
			$actual,
			'The block should have the class name of the preset filter.'
		);
		$this->assertArrayHasKey( 'wp-duotone-' . $kebab_slug, $used_presets, 'The preset should be marked as used.' );
		$this->assertCount( 1, $declarations, 'A single block CSS declaration should be generated.' );
		$this->assertSame(
			array( 'filter' => 'var(--wp--preset--duotone--' . $kebab_slug . ')' ),
			$declarations[0]['declarations'],
			'The block CSS should reference the CSS custom property of the preset.'
		);
		$this->assertStringContainsString(
			'--wp--preset--duotone--' . $kebab_slug . ':url(#wp-duotone-' . $kebab_slug . ');',
			$presets_css,
			'The CSS custom property referenced by the block CSS should be defined.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_render_duotone_support_preset_with_non_kebab_case_slug() {
		return array(
			'camelCase slug, pipe reference'       => array( 'testCamelCase', 'var:preset|duotone|testCamelCase', 'test-camel-case' ),
			'camelCase slug, CSS var reference'    => array( 'testCamelCase', 'var(--wp--preset--duotone--test-camel-case)', 'test-camel-case' ),
			'trailing number slug, pipe reference' => array( 'testpreset1', 'var:preset|duotone|testpreset1', 'testpreset-1' ),
			'kebab-case slug, pipe reference'      => array( 'test-kebab-case', 'var:preset|duotone|test-kebab-case', 'test-kebab-case' ),
		);
	}

	/**
	 * Sets a static property of the WP_Duotone class.
	 *
	 * WP_Duotone is a static class by design, but as of PHP 8.3,
	 * ReflectionProperty::setValue() needs an object.
	 *
	 * @param string $name  The name of the property.
	 * @param mixed  $value The value to set.
	 */
	private function set_duotone_static_property( $name, $value ) {
		$property = new ReflectionProperty( 'WP_Duotone', $name );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		$property->setValue( new WP_Duotone(), $value );
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
			'pipe-slug-camel-case'            => array( 'var:preset|duotone|blueOrange', 'blue-orange' ),
			'css-var-camel-case'              => array( 'var(--wp--preset--duotone--blueOrange)', 'blue-orange' ),
			'pipe-slug-trailing-number'       => array( 'var:preset|duotone|duotone1', 'duotone-1' ),
			'css-var-invalid-slug-chars'      => array( 'var(--wp--preset--duotone--.)', '' ),
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
			'pipe-slug-camel-case'            => array( 'var:preset|duotone|blueOrange', true ),
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
}
