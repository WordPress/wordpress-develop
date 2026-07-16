<?php

/**
 * @group block-supports
 *
 * @covers ::wp_render_custom_css_support_styles
 */
class Tests_Block_Supports_WpRenderCustomCssSupportStyles extends WP_UnitTestCase {
	/**
	 * @var string|null
	 */
	private $test_block_name;

	/**
	 * @var WP_Styles|null
	 */
	private $old_wp_styles;

	public function set_up() {
		parent::set_up();
		$this->test_block_name = null;

		// Use a clean styles queue so tests don't leak registered handles
		// or inline styles into each other. The default-styles callbacks are
		// removed because they expect a fully set up instance.
		$this->old_wp_styles = $GLOBALS['wp_styles'] ?? null;
		remove_action( 'wp_default_styles', 'wp_default_styles' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		$GLOBALS['wp_styles'] = new WP_Styles();
	}

	public function tear_down() {
		if ( $this->test_block_name ) {
			unregister_block_type( $this->test_block_name );
		}
		$this->test_block_name = null;

		$GLOBALS['wp_styles'] = $this->old_wp_styles;
		add_action( 'wp_default_styles', 'wp_default_styles' );
		add_action( 'wp_print_styles', 'print_emoji_styles' );

		parent::tear_down();
	}

	/**
	 * Tests that custom CSS support adds a class name when valid CSS is present.
	 *
	 * @ticket 64544
	 *
	 * @covers ::wp_render_custom_css_support_styles
	 *
	 * @dataProvider data_adds_class_name
	 *
	 * @param string $block_name   The test block name to register.
	 * @param array  $supports     The block support configuration.
	 * @param array  $parsed_block The parsed block data.
	 */
	public function test_adds_class_name( $block_name, $supports, $parsed_block ) {
		$this->test_block_name = $block_name;
		register_block_type(
			$this->test_block_name,
			array(
				'api_version' => 3,
				'attributes'  => array(
					'style' => array(
						'type' => 'object',
					),
				),
				'supports'    => $supports,
			)
		);

		$result = wp_render_custom_css_support_styles( $parsed_block );

		$this->assertArrayHasKey( 'className', $result['attrs'], 'Block should have className added.' );
		$this->assertMatchesRegularExpression( '/wp-custom-css-/', $result['attrs']['className'], 'className should contain wp-custom-css- prefix.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_adds_class_name() {
		return array(
			'class name is added when custom CSS is present'         => array(
				'block_name'   => 'test/custom-css-block',
				'supports'     => array( 'customCSS' => true ),
				'parsed_block' => array(
					'blockName' => 'test/custom-css-block',
					'attrs'     => array(
						'style' => array(
							'css' => 'color: red;',
						),
					),
				),
			),
			'class name is added when support is not explicitly set' => array(
				'block_name'   => 'test/custom-css-default',
				'supports'     => array(),
				'parsed_block' => array(
					'blockName' => 'test/custom-css-default',
					'attrs'     => array(
						'style' => array(
							'css' => 'font-weight: bold;',
						),
					),
				),
			),
			'class name is added for valid CSS with url() values'    => array(
				'block_name'   => 'test/custom-css-valid',
				'supports'     => array( 'customCSS' => true ),
				'parsed_block' => array(
					'blockName' => 'test/custom-css-valid',
					'attrs'     => array(
						'style' => array(
							'css' => 'color: red; background: url("image.png"); font-size: 16px;',
						),
					),
				),
			),
		);
	}

	/**
	 * Tests that existing className is preserved when custom CSS class is added.
	 *
	 * @ticket 64544
	 *
	 * @covers ::wp_render_custom_css_support_styles
	 */
	public function test_preserves_existing_class_name() {
		$this->test_block_name = 'test/custom-css-block-existing';
		register_block_type(
			$this->test_block_name,
			array(
				'api_version' => 3,
				'attributes'  => array(
					'style' => array(
						'type' => 'object',
					),
				),
				'supports'    => array( 'customCSS' => true ),
			)
		);

		$parsed_block = array(
			'blockName' => 'test/custom-css-block-existing',
			'attrs'     => array(
				'className' => 'my-existing-class',
				'style'     => array(
					'css' => 'color: blue;',
				),
			),
		);

		$result = wp_render_custom_css_support_styles( $parsed_block );

		$this->assertStringContainsString( 'my-existing-class', $result['attrs']['className'], 'Existing className should be preserved.' );
		$this->assertMatchesRegularExpression( '/wp-custom-css-/', $result['attrs']['className'], 'className should also contain wp-custom-css- prefix.' );
	}

	/**
	 * Tests that custom CSS support does not add a class name when CSS should not be applied.
	 *
	 * @ticket 64544
	 *
	 * @covers ::wp_render_custom_css_support_styles
	 *
	 * @dataProvider data_does_not_add_class_name
	 *
	 * @param string $block_name   The test block name to register.
	 * @param array  $supports     The block support configuration.
	 * @param array  $parsed_block The parsed block data.
	 */
	public function test_does_not_add_class_name( $block_name, $supports, $parsed_block ) {
		$this->test_block_name = $block_name;
		register_block_type(
			$this->test_block_name,
			array(
				'api_version' => 3,
				'attributes'  => array(
					'style' => array(
						'type' => 'object',
					),
				),
				'supports'    => $supports,
			)
		);

		$result = wp_render_custom_css_support_styles( $parsed_block );

		$this->assertArrayNotHasKey( 'className', $result['attrs'], 'Block should not have className added.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_does_not_add_class_name() {
		return array(
			'support is disabled'            => array(
				'block_name'   => 'test/custom-css-disabled',
				'supports'     => array( 'customCSS' => false ),
				'parsed_block' => array(
					'blockName' => 'test/custom-css-disabled',
					'attrs'     => array(
						'style' => array(
							'css' => 'color: green;',
						),
					),
				),
			),
			'no CSS attribute present'       => array(
				'block_name'   => 'test/custom-css-no-css',
				'supports'     => array( 'customCSS' => true ),
				'parsed_block' => array(
					'blockName' => 'test/custom-css-no-css',
					'attrs'     => array(
						'style' => array(
							'color' => 'red',
						),
					),
				),
			),
			'CSS is empty'                   => array(
				'block_name'   => 'test/custom-css-empty',
				'supports'     => array( 'customCSS' => true ),
				'parsed_block' => array(
					'blockName' => 'test/custom-css-empty',
					'attrs'     => array(
						'style' => array(
							'css' => '',
						),
					),
				),
			),
			'CSS is whitespace only'         => array(
				'block_name'   => 'test/custom-css-whitespace',
				'supports'     => array( 'customCSS' => true ),
				'parsed_block' => array(
					'blockName' => 'test/custom-css-whitespace',
					'attrs'     => array(
						'style' => array(
							'css' => '   ',
						),
					),
				),
			),
			'no style attribute'             => array(
				'block_name'   => 'test/custom-css-no-style',
				'supports'     => array( 'customCSS' => true ),
				'parsed_block' => array(
					'blockName' => 'test/custom-css-no-style',
					'attrs'     => array(),
				),
			),
			'CSS contains HTML opening tags' => array(
				'block_name'   => 'test/custom-css-html-open',
				'supports'     => array( 'customCSS' => true ),
				'parsed_block' => array(
					'blockName' => 'test/custom-css-html-open',
					'attrs'     => array(
						'style' => array(
							'css' => '<script>alert(1)</script>',
						),
					),
				),
			),
			'CSS contains HTML closing tags' => array(
				'block_name'   => 'test/custom-css-html-close',
				'supports'     => array( 'customCSS' => true ),
				'parsed_block' => array(
					'blockName' => 'test/custom-css-html-close',
					'attrs'     => array(
						'style' => array(
							'css' => 'color: red;</style><script>alert(1)</script>',
						),
					),
				),
			),
		);
	}

	/**
	 * Tests that CSS is enqueued only once when the same block is rendered
	 * multiple times, as happens inside a Query Loop.
	 *
	 * @ticket 65268
	 *
	 * @covers ::wp_render_custom_css_support_styles
	 */
	public function test_css_not_duplicated_on_repeated_renders(): void {
		$this->test_block_name = 'test/custom-css-query-loop-dedup';
		register_block_type(
			$this->test_block_name,
			array(
				'api_version' => 3,
				'attributes'  => array(
					'style' => array(
						'type' => 'object',
					),
				),
				'supports'    => array( 'customCSS' => true ),
			)
		);

		$parsed_block = array(
			'blockName' => 'test/custom-css-query-loop-dedup',
			'attrs'     => array(
				'style' => array(
					'css' => 'font-size: 2em; /* query-loop-dedup-test */',
				),
			),
		);

		// Simulate the same block being rendered multiple times inside a Query Loop.
		$result = wp_render_custom_css_support_styles( $parsed_block );
		wp_render_custom_css_support_styles( $parsed_block );
		wp_render_custom_css_support_styles( $parsed_block );

		// Extract the generated class name from the first render's result.
		$this->assertSame( 1, preg_match( '/(?:^|\s)(wp-custom-css-\S+)/', $result['attrs']['className'] ?? '', $matches ) );
		$class_name = $matches[1];

		// Count how many times the CSS selector for this block appears in the enqueued inline styles.
		$inline_styles = (array) wp_styles()->get_data( 'wp-block-custom-css', 'after' );
		$occurrences   = 0;
		foreach ( $inline_styles as $style ) {
			$this->assertIsString( $style );
			$occurrences += substr_count( $style, '.' . $class_name );
		}

		$this->assertSame(
			1,
			$occurrences,
			'CSS should be enqueued exactly once even when the same block renders multiple times.'
		);
	}

	/**
	 * Tests that custom CSS styles print after block style variation styles,
	 * regardless of enqueue order, so that custom CSS wins the cascade at
	 * equal specificity.
	 *
	 * @ticket 65641
	 *
	 * @covers ::wp_render_custom_css_support_styles
	 */
	public function test_custom_css_prints_after_block_style_variation_styles() {
		wp_register_style( 'wp-block-library', false );
		wp_register_style( 'global-styles', false );

		$this->test_block_name = 'test/custom-css-print-order';
		register_block_type(
			$this->test_block_name,
			array(
				'api_version' => 3,
				'attributes'  => array(
					'style' => array(
						'type' => 'object',
					),
				),
				'supports'    => array( 'customCSS' => true ),
			)
		);

		$parsed_block = array(
			'blockName' => 'test/custom-css-print-order',
			'attrs'     => array(
				'style' => array(
					'css' => 'border-style: double;',
				),
			),
		);

		wp_render_custom_css_support_styles( $parsed_block );

		// Mimic the block style variation support registering its per-instance
		// styles, as done in wp_render_block_style_variation_support_styles().
		wp_register_style( 'block-style-variation-styles', false, array( 'wp-block-library', 'global-styles' ) );
		wp_add_inline_style( 'block-style-variation-styles', ':root :where(.is-style-test-variation){border-style: dotted;}' );

		// Enqueue custom CSS first to prove the declared dependency decides
		// the print order, not the enqueue order.
		wp_enqueue_style( 'wp-block-custom-css' );
		wp_enqueue_style( 'block-style-variation-styles' );

		$output = get_echo( 'wp_print_styles' );

		$variation_position  = strpos( $output, 'border-style: dotted' );
		$custom_css_position = strpos( $output, 'border-style: double' );

		$this->assertNotFalse( $variation_position, 'Block style variation styles should be printed.' );
		$this->assertNotFalse( $custom_css_position, 'Custom CSS should be printed.' );
		$this->assertLessThan( $custom_css_position, $variation_position, 'Block style variation styles should print before custom CSS so custom CSS wins ties at equal specificity.' );
	}

	/**
	 * Tests that custom CSS still prints when no block style variation styles
	 * were registered on the page. The `block-style-variation-styles` handle
	 * is normally registered lazily while rendering a block with a variation,
	 * and a style with an unregistered dependency is never printed.
	 *
	 * @ticket 65641
	 *
	 * @covers ::wp_render_custom_css_support_styles
	 */
	public function test_custom_css_prints_without_block_style_variation_styles() {
		wp_register_style( 'wp-block-library', false );
		wp_register_style( 'global-styles', false );

		$this->test_block_name = 'test/custom-css-no-variations';
		register_block_type(
			$this->test_block_name,
			array(
				'api_version' => 3,
				'attributes'  => array(
					'style' => array(
						'type' => 'object',
					),
				),
				'supports'    => array( 'customCSS' => true ),
			)
		);

		$parsed_block = array(
			'blockName' => 'test/custom-css-no-variations',
			'attrs'     => array(
				'style' => array(
					'css' => 'color: teal;',
				),
			),
		);

		wp_render_custom_css_support_styles( $parsed_block );
		wp_enqueue_style( 'wp-block-custom-css' );

		$output = get_echo( 'wp_print_styles' );

		$this->assertStringContainsString( 'color: teal', $output, 'Custom CSS should print even when no block style variation styles exist on the page.' );
	}
}
