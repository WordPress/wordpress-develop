<?php

/**
 * @group block-supports
 *
 * @covers ::wp_render_elements_support_styles
 */
class Tests_Block_Supports_WpRenderElementsSupportStyles extends WP_UnitTestCase {
	/**
	 * @var string|null
	 */
	private $test_block_name;

	public function tear_down() {
		unregister_block_type( $this->test_block_name );
		$this->test_block_name = null;
		parent::tear_down();
	}

	/**
	 * Tests that elements block support generates appropriate styles.
	 *
	 * @ticket 59555
	 * @ticket 60557
	 *
	 * @covers ::wp_render_elements_support_styles
	 *
	 * @dataProvider data_elements_block_support_styles
	 *
	 * @param mixed  $color_settings  The color block support settings used for elements support.
	 * @param mixed  $elements_styles The elements styles within the block attributes.
	 * @param string $expected_styles Expected styles enqueued by the style engine.
	 */
	public function test_elements_block_support_styles( $color_settings, $elements_styles, $expected_styles ) {
		$this->test_block_name = 'test/element-block-supports';

		register_block_type(
			$this->test_block_name,
			array(
				'api_version' => 3,
				'attributes'  => array(
					'style' => array(
						'type' => 'object',
					),
				),
				'supports'    => array(
					'color' => $color_settings,
				),
			)
		);

		$block = array(
			'blockName' => $this->test_block_name,
			'attrs'     => array(
				'style' => array(
					'elements' => $elements_styles,
				),
			),
		);

		wp_render_elements_support_styles( $block );
		$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

		$this->assertMatchesRegularExpression(
			$expected_styles,
			$actual_stylesheet,
			'Elements style rules output should be correct'
		);
	}

	/**
	 * Tests that identical blocks with different elements styles
	 * generate distinct class names to avoid CSS cascade conflicts.
	 *
	 * @ticket 65435
	 *
	 * @covers ::wp_get_elements_class_name
	 */
	public function test_elements_block_support_styles_with_duplicate_blocks(): void {
		$this->test_block_name = 'test/element-block-supports';

		register_block_type(
			$this->test_block_name,
			array(
				'api_version' => 3,
				'attributes'  => array(
					'style' => array(
						'type' => 'object',
					),
				),
				'supports'    => array(
					'color' => array(
						'link' => true,
					),
				),
			)
		);

		$block = array(
			'blockName' => $this->test_block_name,
			'attrs'     => array(
				'style' => array(
					'elements' => array(
						'link' => array(
							'color' => array(
								'text' => 'blue',
							),
						),
					),
				),
			),
		);

		// Process two identical blocks with the same elements styles.
		$count = 2;
		for ( $i = 0; $i < $count; $i++ ) {
			wp_render_elements_support_styles( $block );
		}
		$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

		// Count the number of distinct class names to confirm uniqueness.
		$this->assertSame( $count, preg_match_all( '/\.wp-elements-(\d+)/', $actual_stylesheet, $matches ) );
		$unique_classes = array_unique( $matches[1] );
		$this->assertCount( $count, $unique_classes, 'Both blocks should produce distinct class names' );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_elements_block_support_styles() {
		$color_styles    = array(
			'text'       => 'var:preset|color|vivid-red',
			'background' => '#fff',
		);
		$color_css_rules = preg_quote( '{color:var(--wp--preset--color--vivid-red);background-color:#fff;}' );

		return array(
			'button element styles are not applied if serialization is skipped' => array(
				'color_settings'  => array(
					'button'                          => true,
					'__experimentalSkipSerialization' => true,
				),
				'elements_styles' => array(
					'button' => array( 'color' => $color_styles ),
				),
				'expected_styles' => '/^$/',
			),
			'link element styles are not applied if serialization is skipped' => array(
				'color_settings'  => array(
					'link'                            => true,
					'__experimentalSkipSerialization' => true,
				),
				'elements_styles' => array(
					'link' => array(
						'color'  => $color_styles,
						':hover' => array(
							'color' => $color_styles,
						),
					),
				),
				'expected_styles' => '/^$/',
			),
			'heading element styles are not applied if serialization is skipped' => array(
				'color_settings'  => array(
					'heading'                         => true,
					'__experimentalSkipSerialization' => true,
				),
				'elements_styles' => array(
					'heading' => array( 'color' => $color_styles ),
					'h1'      => array( 'color' => $color_styles ),
					'h2'      => array( 'color' => $color_styles ),
					'h3'      => array( 'color' => $color_styles ),
					'h4'      => array( 'color' => $color_styles ),
					'h5'      => array( 'color' => $color_styles ),
					'h6'      => array( 'color' => $color_styles ),
				),
				'expected_styles' => '/^$/',
			),
			'button element styles are applied'          => array(
				'color_settings'  => array( 'button' => true ),
				'elements_styles' => array(
					'button' => array( 'color' => $color_styles ),
				),
				'expected_styles' => '/^.wp-elements-\d+ .wp-element-button, .wp-elements-\d+ .wp-block-button__link' . $color_css_rules . '$/',
			),
			'link element styles are applied'            => array(
				'color_settings'  => array( 'link' => true ),
				'elements_styles' => array(
					'link' => array(
						'color'  => $color_styles,
						':hover' => array(
							'color' => $color_styles,
						),
					),
				),
				'expected_styles' => '/^.wp-elements-\d+ a:where\(:not\(.wp-element-button\)\)' . $color_css_rules .
					'.wp-elements-\d+ a:where\(:not\(.wp-element-button\)\):hover' . $color_css_rules . '$/',
			),
			'generic heading element styles are applied' => array(
				'color_settings'  => array( 'heading' => true ),
				'elements_styles' => array(
					'heading' => array( 'color' => $color_styles ),
				),
				'expected_styles' => '/^.wp-elements-\d+ h1, .wp-elements-\d+ h2, .wp-elements-\d+ h3, .wp-elements-\d+ h4, .wp-elements-\d+ h5, .wp-elements-\d+ h6' . $color_css_rules . '$/',
			),
			'individual heading element styles are applied' => array(
				'color_settings'  => array( 'heading' => true ),
				'elements_styles' => array(
					'h1' => array( 'color' => $color_styles ),
					'h2' => array( 'color' => $color_styles ),
					'h3' => array( 'color' => $color_styles ),
					'h4' => array( 'color' => $color_styles ),
					'h5' => array( 'color' => $color_styles ),
					'h6' => array( 'color' => $color_styles ),
				),
				'expected_styles' => '/^.wp-elements-\d+ h1' . $color_css_rules .
					'.wp-elements-\d+ h2' . $color_css_rules .
					'.wp-elements-\d+ h3' . $color_css_rules .
					'.wp-elements-\d+ h4' . $color_css_rules .
					'.wp-elements-\d+ h5' . $color_css_rules .
					'.wp-elements-\d+ h6' . $color_css_rules . '$/',
			),
		);
	}
}
