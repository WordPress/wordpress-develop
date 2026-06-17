<?php
/**
 * Tests the states block support.
 *
 * @package WordPress
 * @subpackage Block Supports
 * @since 7.1.0
 *
 * @group block-supports
 *
 * @covers ::wp_render_block_states_support
 */
class Tests_Block_Supports_States extends WP_UnitTestCase {

	/**
	 * @var string|null
	 */
	private $test_block_name;

	public function set_up() {
		parent::set_up();
		$this->test_block_name = null;
		WP_Style_Engine_CSS_Rules_Store::remove_all_stores();
	}

	public function tear_down() {
		if ( $this->test_block_name ) {
			unregister_block_type( $this->test_block_name );
		}
		$this->test_block_name = null;
		WP_Style_Engine_CSS_Rules_Store::remove_all_stores();
		parent::tear_down();
	}

	/**
	 * Registers a block for tests when the block is not already registered.
	 *
	 * @param string $block_name Block name.
	 * @param array  $selectors  Optional block selectors, e.g. array( 'root' => '.foo .bar' ).
	 * @param array  $supports   Optional block supports.
	 * @return WP_Block_Type
	 */
	private function ensure_block_registered( $block_name, $selectors = array(), $supports = array() ) {
		$registered_block = WP_Block_Type_Registry::get_instance()->get_registered( $block_name );
		if ( $registered_block ) {
			return $registered_block;
		}

		$this->test_block_name = $block_name;
		$args                  = array(
			'api_version' => 3,
			'attributes'  => array(
				'style' => array(
					'type' => 'object',
				),
			),
		);
		if ( ! empty( $selectors ) ) {
			$args['selectors'] = $selectors;
		}
		if ( ! empty( $supports ) ) {
			$args['supports'] = $supports;
		}
		register_block_type( $block_name, $args );

		return WP_Block_Type_Registry::get_instance()->get_registered( $block_name );
	}

	/**
	 * Mirrors the CSS-building logic in wp_render_block_states_support()
	 * to produce the unique scoped class name for a given map of state => style arrays.
	 * CSS is now registered with the style engine store rather than injected inline.
	 *
	 * @param array $state_styles Map of state to style array (e.g. `[':hover' => ['color' => [...]]]`).
	 * @param string $block_name  Block name.
	 * @return array { unique_class: string }
	 */
	private function build_expected_state_output( $state_styles, $block_name = 'core/button' ) {
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( $block_name );
		$css_rules  = wp_get_block_state_style_rules( $state_styles, $block_type );

		return array(
			'unique_class' => wp_get_block_state_unique_class( $block_name, $css_rules ),
		);
	}

	/**
	 * Tests that fallback border-style declarations are added after CSS generation.
	 *
	 * @covers ::wp_get_state_declarations_with_fallback_border_styles
	 *
	 * @ticket 65239
	 */
	public function test_adds_fallback_border_style_declarations() {
		$actual = wp_get_state_declarations_with_fallback_border_styles(
			array(
				'border-color'     => '#000000',
				'border-top-width' => '2px',
			)
		);

		$this->assertSame(
			array(
				'border-color'     => '#000000',
				'border-top-width' => '2px',
				'border-style'     => 'solid',
				'border-top-style' => 'solid',
			),
			$actual
		);
	}

	/**
	 * Tests that authored border-style declarations are preserved.
	 *
	 * @covers ::wp_get_state_declarations_with_fallback_border_styles
	 *
	 * @ticket 65239
	 */
	public function test_preserves_authored_border_style_declarations() {
		$actual = wp_get_state_declarations_with_fallback_border_styles(
			array(
				'border-color'      => '#000000',
				'border-style'      => 'dashed !important',
				'border-left-width' => '2px',
			)
		);

		$this->assertSame(
			array(
				'border-color'      => '#000000',
				'border-style'      => 'dashed !important',
				'border-left-width' => '2px',
			),
			$actual
		);
	}

	/**
	 * Tests that background-image reset is added when a state sets a solid background-color.
	 *
	 * @covers ::wp_get_state_declarations_with_background_resets
	 *
	 * @ticket 65239
	 */
	public function test_adds_background_image_reset_for_solid_background_color() {
		$actual = wp_get_state_declarations_with_background_resets(
			array(
				'background-color' => '#ff0000 !important',
			)
		);

		$this->assertSame(
			array(
				'background-color' => '#ff0000 !important',
				'background-image' => 'unset !important',
			),
			$actual
		);
	}

	/**
	 * Tests that background-image reset is not added when the state also sets a legacy gradient.
	 *
	 * @covers ::wp_get_state_declarations_with_background_resets
	 *
	 * @ticket 65239
	 */
	public function test_no_background_image_reset_when_state_sets_legacy_gradient() {
		$actual = wp_get_state_declarations_with_background_resets(
			array(
				'background-color' => '#ff0000 !important',
				'background'       => 'linear-gradient(135deg, #ff0000, #0000ff) !important',
			)
		);

		$this->assertSame(
			array(
				'background-color' => '#ff0000 !important',
				'background'       => 'linear-gradient(135deg, #ff0000, #0000ff) !important',
			),
			$actual
		);
	}

	/**
	 * Tests that background-image reset is not added when the state also sets a modern gradient.
	 *
	 * @covers ::wp_get_state_declarations_with_background_resets
	 *
	 * @ticket 65239
	 */
	public function test_no_background_image_reset_when_state_sets_modern_gradient() {
		$actual = wp_get_state_declarations_with_background_resets(
			array(
				'background-color' => '#ff0000 !important',
				'background-image' => 'linear-gradient(135deg, #ff0000, #0000ff) !important',
			)
		);

		$this->assertSame(
			array(
				'background-color' => '#ff0000 !important',
				'background-image' => 'linear-gradient(135deg, #ff0000, #0000ff) !important',
			),
			$actual
		);
	}

	/**
	 * Tests that declarations without background-color are returned unchanged.
	 *
	 * @covers ::wp_get_state_declarations_with_background_resets
	 *
	 * @ticket 65239
	 */
	public function test_no_background_reset_when_no_background_color() {
		$input  = array(
			'color' => '#ff0000 !important',
		);
		$actual = wp_get_state_declarations_with_background_resets( $input );

		$this->assertSame( $input, $actual );
	}

	/**
	 * Tests that fallback dimension styles are added for aspect ratio.
	 *
	 * @covers ::wp_get_state_style_with_fallback_dimension_styles
	 *
	 * @ticket 65239
	 */
	public function test_adds_fallback_dimension_styles_for_aspect_ratio() {
		$actual = wp_get_state_style_with_fallback_dimension_styles(
			array(
				'dimensions' => array(
					'aspectRatio' => '16/9',
				),
			)
		);

		$this->assertSame(
			array(
				'dimensions' => array(
					'aspectRatio' => '16/9',
					'minHeight'   => 'unset',
					'height'      => 'unset',
				),
			),
			$actual
		);
	}

	/**
	 * Tests that fallback dimension styles are not added for the default aspect ratio.
	 *
	 * @covers ::wp_get_state_style_with_fallback_dimension_styles
	 *
	 * @ticket 65239
	 */
	public function test_does_not_add_fallback_dimension_styles_for_default_aspect_ratio() {
		$actual = wp_get_state_style_with_fallback_dimension_styles(
			array(
				'dimensions' => array(
					'aspectRatio' => 'auto',
				),
			)
		);

		$this->assertSame(
			array(
				'dimensions' => array(
					'aspectRatio' => 'auto',
				),
			),
			$actual
		);
	}

	/**
	 * Tests that fallback aspectRatio styles are added for height.
	 *
	 * @covers ::wp_get_state_style_with_fallback_dimension_styles
	 *
	 * @ticket 65239
	 */
	public function test_adds_fallback_aspect_ratio_style_for_height() {
		$actual = wp_get_state_style_with_fallback_dimension_styles(
			array(
				'dimensions' => array(
					'height' => '20rem',
				),
			)
		);

		$this->assertSame(
			array(
				'dimensions' => array(
					'height'      => '20rem',
					'aspectRatio' => 'unset',
				),
			),
			$actual
		);
	}

	/**
	 * Tests that modifier classes on the first compound selector are preserved
	 * when state selectors are scoped to the block wrapper.
	 *
	 * @covers ::wp_build_state_selector
	 *
	 * @ticket 65239
	 */
	public function test_build_state_selector_preserves_first_compound_modifier_classes() {
		$actual = wp_build_state_selector(
			'.wp-states-test',
			'.wp-block-search.wp-block-search__button-outside .wp-block-search__input',
			':hover'
		);

		$this->assertSame(
			'.wp-states-test.wp-block-search__button-outside .wp-block-search__input:hover',
			$actual
		);
	}

	/**
	 * Tests that child combinators without surrounding spaces are preserved when
	 * state selectors are scoped to the block wrapper.
	 *
	 * @covers ::wp_build_state_selector
	 *
	 * @ticket 65239
	 */
	public function test_build_state_selector_preserves_child_combinator_without_spaces() {
		$actual = wp_build_state_selector(
			'.wp-states-test',
			'.wp-block-foo>.inner',
			':hover'
		);

		$this->assertSame(
			'.wp-states-test>.inner:hover',
			$actual
		);
	}

	/**
	 * Tests that selector lists are split without splitting selector-function arguments.
	 *
	 * @covers ::wp_build_state_selector
	 *
	 * @ticket 65239
	 */
	public function test_build_state_selector_splits_selector_lists_without_splitting_selector_function_arguments() {
		$actual = wp_build_state_selector(
			'.wp-states-test',
			'.wp-block-example:not(.foo, .bar) .inner, .wp-block-example .fallback',
			':hover'
		);

		$this->assertSame(
			'.wp-states-test:not(.foo, .bar) .inner:hover, .wp-states-test .fallback:hover',
			$actual
		);
	}

	/**
	 * Tests that preset values are converted to CSS custom property references.
	 *
	 * @covers ::wp_normalize_state_preset_vars
	 *
	 * @ticket 65239
	 */
	public function test_converts_state_preset_vars_to_css_vars() {
		$actual = wp_normalize_state_preset_vars(
			array(
				'border' => array(
					'color' => 'var:preset|color|accent-1',
				),
			)
		);

		$this->assertSame(
			array(
				'border' => array(
					'color' => 'var(--wp--preset--color--accent-1)',
				),
			),
			$actual
		);
	}

	/**
	 * Tests that block content is returned unchanged when the block name is missing.
	 *
	 * @covers ::wp_render_block_states_support
	 *
	 * @ticket 65239
	 */
	public function test_returns_unchanged_when_block_name_missing() {
		$block_content = '<div class="wp-block-test">Hello</div>';
		$block         = array(
			'blockName' => '',
			'attrs'     => array(),
		);

		$actual = wp_render_block_states_support( $block_content, $block );

		$this->assertSame( $block_content, $actual );
	}

	/**
	 * Tests that block content is returned unchanged when content is empty.
	 *
	 * @covers ::wp_render_block_states_support
	 *
	 * @ticket 65239
	 */
	public function test_returns_unchanged_when_block_content_empty() {
		$this->ensure_block_registered( 'core/button' );

		$block = array(
			'blockName' => 'core/button',
			'attrs'     => array(
				'style' => array(
					':hover' => array( 'color' => array( 'text' => '#ff0000' ) ),
				),
			),
		);

		$actual = wp_render_block_states_support( '', $block );

		$this->assertSame( '', $actual );
	}

	/**
	 * Tests that block content is returned unchanged when the block has no configured pseudo-states.
	 *
	 * @covers ::wp_render_block_states_support
	 *
	 * @ticket 65239
	 */
	public function test_returns_unchanged_when_block_has_no_configured_pseudo_states() {
		$this->test_block_name = 'test/no-pseudo-state-config';
		register_block_type(
			$this->test_block_name,
			array(
				'api_version' => 3,
				'attributes'  => array(
					'style' => array( 'type' => 'object' ),
				),
				'supports'    => array(),
			)
		);

		$block_content = '<div class="wp-block-test">Hello</div>';
		$block         = array(
			'blockName' => 'test/no-pseudo-state-config',
			'attrs'     => array(
				'style' => array(
					':hover' => array( 'color' => array( 'text' => '#ff0000' ) ),
				),
			),
		);

		$actual = wp_render_block_states_support( $block_content, $block );

		$this->assertSame( $block_content, $actual );
	}

	/**
	 * Tests that block content is returned unchanged when no pseudo-state styles are set.
	 *
	 * @covers ::wp_render_block_states_support
	 *
	 * @ticket 65239
	 */
	public function test_returns_unchanged_when_no_state_styles_set() {
		$this->ensure_block_registered( 'core/button' );

		$block_content = '<div class="wp-block-test">Hello</div>';
		$block         = array(
			'blockName' => 'core/button',
			'attrs'     => array(
				'style' => array(
					'color' => array( 'text' => '#000000' ),
				),
			),
		);

		$actual = wp_render_block_states_support( $block_content, $block );

		$this->assertSame( $block_content, $actual );
	}

	/**
	 * Tests that block content is returned unchanged when the pseudo-state key is an empty array.
	 *
	 * @covers ::wp_render_block_states_support
	 *
	 * @ticket 65239
	 */
	public function test_returns_unchanged_when_state_style_is_empty_array() {
		$this->ensure_block_registered( 'core/button' );

		$block_content = '<div class="wp-block-test">Hello</div>';
		$block         = array(
			'blockName' => 'core/button',
			'attrs'     => array(
				'style' => array(
					':hover' => array(),
				),
			),
		);

		$actual = wp_render_block_states_support( $block_content, $block );

		$this->assertSame( $block_content, $actual );
	}

	/**
	 * Tests that hover text color generates scoped CSS with !important.
	 *
	 * @covers ::wp_render_block_states_support
	 *
	 * @ticket 65239
	 */
	public function test_hover_text_color_generates_scoped_css() {
		$this->ensure_block_registered( 'core/button' );

		$block_content = '<div class="wp-block-test">Hello</div>';
		$state_styles  = array( ':hover' => array( 'color' => array( 'text' => '#e6ffe8' ) ) );
		$block         = array(
			'blockName' => 'core/button',
			'attrs'     => array( 'style' => $state_styles ),
		);

		$parts    = $this->build_expected_state_output( $state_styles );
		$expected = '<div class="wp-block-test ' . $parts['unique_class'] . '">Hello</div>';
		$actual   = wp_render_block_states_support( $block_content, $block );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Tests that hover background color generates scoped CSS.
	 *
	 * @covers ::wp_render_block_states_support
	 *
	 * @ticket 65239
	 */
	public function test_hover_background_color_generates_scoped_css() {
		$this->ensure_block_registered( 'core/button' );

		$block_content = '<div class="wp-block-test">Hello</div>';
		$state_styles  = array( ':hover' => array( 'color' => array( 'background' => '#ff00d0' ) ) );
		$block         = array(
			'blockName' => 'core/button',
			'attrs'     => array( 'style' => $state_styles ),
		);

		$parts    = $this->build_expected_state_output( $state_styles );
		$expected = '<div class="wp-block-test ' . $parts['unique_class'] . '">Hello</div>';
		$actual   = wp_render_block_states_support( $block_content, $block );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Tests that hover text and background color both appear in a single rule.
	 *
	 * @covers ::wp_render_block_states_support
	 *
	 * @ticket 65239
	 */
	public function test_hover_text_and_background_color_in_same_rule() {
		$this->ensure_block_registered( 'core/button' );

		$block_content = '<div class="wp-block-test">Hello</div>';
		$state_styles  = array(
			':hover' => array(
				'color' => array(
					'background' => '#ff00d0',
					'text'       => '#e6ffe8',
				),
			),
		);
		$block         = array(
			'blockName' => 'core/button',
			'attrs'     => array( 'style' => $state_styles ),
		);

		$parts    = $this->build_expected_state_output( $state_styles );
		$expected = '<div class="wp-block-test ' . $parts['unique_class'] . '">Hello</div>';
		$actual   = wp_render_block_states_support( $block_content, $block );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Tests that a font family stored as a preset reference is resolved to a CSS
	 * custom property in the generated style tag.
	 *
	 * @covers ::wp_render_block_states_support
	 *
	 * @ticket 65239
	 */
	public function test_hover_font_family_preset_reference_generates_css_custom_property() {
		$this->ensure_block_registered( 'core/button' );

		$block_content = '<div class="wp-block-test">Hello</div>';
		$state_styles  = array(
			':hover' => array(
				'typography' => array( 'fontFamily' => 'var:preset|font-family|heading' ),
			),
		);
		$block         = array(
			'blockName' => 'core/button',
			'attrs'     => array( 'style' => $state_styles ),
		);

		$parts    = $this->build_expected_state_output( $state_styles );
		$expected = '<div class="wp-block-test ' . $parts['unique_class'] . '">Hello</div>';
		$actual   = wp_render_block_states_support( $block_content, $block );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Tests that hover font size generates scoped CSS.
	 *
	 * @covers ::wp_render_block_states_support
	 *
	 * @ticket 65239
	 */
	public function test_hover_font_size_generates_scoped_css() {
		$this->ensure_block_registered( 'core/button' );

		$block_content = '<div class="wp-block-test">Hello</div>';
		$state_styles  = array(
			':hover' => array(
				'typography' => array( 'fontSize' => '1.5rem' ),
			),
		);
		$block         = array(
			'blockName' => 'core/button',
			'attrs'     => array( 'style' => $state_styles ),
		);

		$parts    = $this->build_expected_state_output( $state_styles );
		$expected = '<div class="wp-block-test ' . $parts['unique_class'] . '">Hello</div>';
		$actual   = wp_render_block_states_support( $block_content, $block );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Tests that hover border width and color generate a scoped style tag.
	 *
	 * @covers ::wp_render_block_states_support
	 *
	 * @ticket 65239
	 */
	public function test_hover_border_width_and_color_generate_scoped_css() {
		$this->ensure_block_registered( 'core/button' );

		$block_content = '<div class="wp-block-test">Hello</div>';
		$state_styles  = array(
			':hover' => array(
				'border' => array(
					'width' => '2px',
					'color' => '#000000',
				),
			),
		);
		$block         = array(
			'blockName' => 'core/button',
			'attrs'     => array( 'style' => $state_styles ),
		);

		$parts    = $this->build_expected_state_output( $state_styles );
		$expected = '<div class="wp-block-test ' . $parts['unique_class'] . '">Hello</div>';
		$actual   = wp_render_block_states_support( $block_content, $block );

		$this->assertSame( $expected, $actual );

		$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );
		$this->assertStringContainsString(
			'border-width:2px !important;',
			$actual_stylesheet
		);
		$this->assertStringContainsString(
			'border-style:solid;',
			$actual_stylesheet
		);
		$this->assertStringNotContainsString(
			'border-style:solid !important;',
			$actual_stylesheet
		);
	}

	/**
	 * Tests that explicitly-authored hover border style declarations use !important.
	 *
	 * @covers ::wp_render_block_states_support
	 *
	 * @ticket 65239
	 */
	public function test_hover_authored_border_style_generates_important_css_declaration() {
		$this->ensure_block_registered( 'core/button' );

		$block_content = '<div class="wp-block-test">Hello</div>';
		$state_styles  = array(
			':hover' => array(
				'border' => array(
					'style' => 'solid',
				),
			),
		);
		$block         = array(
			'blockName' => 'core/button',
			'attrs'     => array( 'style' => $state_styles ),
		);

		wp_render_block_states_support( $block_content, $block );
		$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

		$this->assertStringContainsString(
			'border-style:solid !important;',
			$actual_stylesheet
		);
	}

	/**
	 * Tests that explicitly-authored side border style declarations use !important.
	 *
	 * @covers ::wp_render_block_states_support
	 *
	 * @ticket 65239
	 */
	public function test_hover_authored_side_border_style_generates_important_css_declaration() {
		$this->ensure_block_registered( 'core/button' );

		$block_content = '<div class="wp-block-test">Hello</div>';
		$state_styles  = array(
			':hover' => array(
				'border' => array(
					'top' => array(
						'style' => 'dashed',
					),
				),
			),
		);
		$block         = array(
			'blockName' => 'core/button',
			'attrs'     => array( 'style' => $state_styles ),
		);

		wp_render_block_states_support( $block_content, $block );
		$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

		$this->assertStringContainsString(
			'border-top-style:dashed !important;',
			$actual_stylesheet
		);
	}

	/**
	 * Tests that hover side border color declarations use !important.
	 *
	 * @covers ::wp_render_block_states_support
	 *
	 * @ticket 65239
	 */
	public function test_hover_side_border_color_generates_important_css_declaration() {
		$this->ensure_block_registered( 'core/button' );

		$block_content = '<div class="wp-block-test">Hello</div>';
		$state_styles  = array(
			':hover' => array(
				'border' => array(
					'top' => array(
						'color' => '#0000ff',
					),
				),
			),
		);
		$block         = array(
			'blockName' => 'core/button',
			'attrs'     => array( 'style' => $state_styles ),
		);

		wp_render_block_states_support( $block_content, $block );
		$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

		$this->assertStringContainsString(
			'border-top-color:#0000ff !important;',
			$actual_stylesheet
		);
		$this->assertStringContainsString(
			'border-top-style:solid;',
			$actual_stylesheet
		);
	}

	/**
	 * Tests that a preset hover border color is emitted as a CSS declaration.
	 *
	 * @covers ::wp_render_block_states_support
	 *
	 * @ticket 65239
	 */
	public function test_hover_preset_border_color_generates_css_declaration() {
		$this->ensure_block_registered( 'core/button' );

		$block_content = '<div class="wp-block-test">Hello</div>';
		$state_styles  = array(
			':hover' => array(
				'border' => array(
					'color' => 'var:preset|color|accent-1',
				),
			),
		);
		$block         = array(
			'blockName' => 'core/button',
			'attrs'     => array( 'style' => $state_styles ),
		);

		$parts = $this->build_expected_state_output( $state_styles );
		wp_render_block_states_support( $block_content, $block );
		$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

		$this->assertStringContainsString(
			'.' . $parts['unique_class'] . ' .wp-block-button__link:hover{',
			$actual_stylesheet
		);
		$this->assertStringContainsString(
			'border-color:var(--wp--preset--color--accent-1) !important;',
			$actual_stylesheet
		);
		$this->assertStringContainsString(
			'border-style:solid;',
			$actual_stylesheet
		);
	}

	/**
	 * Tests that hover border radius generates scoped CSS.
	 *
	 * @covers ::wp_render_block_states_support
	 *
	 * @ticket 65239
	 */
	public function test_hover_border_radius_generates_scoped_css() {
		$this->ensure_block_registered( 'core/button' );

		$block_content = '<div class="wp-block-test">Hello</div>';
		$state_styles  = array(
			':hover' => array(
				'border' => array( 'radius' => '8px' ),
			),
		);
		$block         = array(
			'blockName' => 'core/button',
			'attrs'     => array( 'style' => $state_styles ),
		);

		$parts    = $this->build_expected_state_output( $state_styles );
		$expected = '<div class="wp-block-test ' . $parts['unique_class'] . '">Hello</div>';
		$actual   = wp_render_block_states_support( $block_content, $block );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Tests that multiple states each generate a separate scoped CSS rule.
	 *
	 * @covers ::wp_render_block_states_support
	 *
	 * @ticket 65239
	 */
	public function test_multiple_states_generate_separate_css_rules() {
		$this->ensure_block_registered( 'core/button' );

		$block_content = '<div class="wp-block-test">Hello</div>';
		$state_styles  = array(
			':hover'         => array( 'color' => array( 'text' => '#ff0000' ) ),
			':focus'         => array( 'color' => array( 'text' => '#00ff00' ) ),
			':focus-visible' => array( 'color' => array( 'text' => '#0000ff' ) ),
		);
		$block         = array(
			'blockName' => 'core/button',
			'attrs'     => array( 'style' => $state_styles ),
		);

		$parts    = $this->build_expected_state_output( $state_styles );
		$expected = '<div class="wp-block-test ' . $parts['unique_class'] . '">Hello</div>';
		$actual   = wp_render_block_states_support( $block_content, $block );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Tests that unconfigured pseudo-state keys are ignored.
	 *
	 * @covers ::wp_render_block_states_support
	 *
	 * @ticket 65239
	 */
	public function test_unconfigured_pseudo_state_is_ignored() {
		$this->ensure_block_registered( 'core/button' );

		$block_content = '<div class="wp-block-test">Hello</div>';
		$block         = array(
			'blockName' => 'core/button',
			'attrs'     => array(
				'style' => array(
					':visited' => array( 'color' => array( 'text' => '#ff0000' ) ),
				),
			),
		);

		$actual = wp_render_block_states_support( $block_content, $block );

		$this->assertSame( $block_content, $actual );
	}

	/**
	 * Tests that a responsive root state generates media-query scoped CSS.
	 *
	 * @ticket 65239
	 */
	public function test_responsive_root_state_generates_media_query_scoped_css() {
		$this->ensure_block_registered( 'test/responsive-root-state' );

		$block_content = '<div class="wp-block-test">Hello</div>';
		$block         = array(
			'blockName' => 'test/responsive-root-state',
			'attrs'     => array(
				'style' => array(
					'mobile' => array(
						'color' => array(
							'text' => '#ff0000',
						),
					),
				),
			),
		);

		$actual = wp_render_block_states_support( $block_content, $block );

		$this->assertMatchesRegularExpression(
			'/^<div class="wp-block-test (wp-states-[a-f0-9]{8})">Hello<\/div>$/',
			$actual
		);
		preg_match( '/wp-states-[a-f0-9]{8}/', $actual, $matches );
		$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

		$this->assertStringContainsString(
			'@media (width <= 480px){.' . $matches[0] . '{color:#ff0000 !important;}}',
			$actual_stylesheet
		);
	}

	/**
	 * Tests that a responsive element color generates media-query scoped CSS.
	 *
	 * @covers ::wp_render_block_states_support
	 *
	 * @ticket 65164
	 */
	public function test_responsive_element_color_generates_media_query_scoped_css() {
		$this->ensure_block_registered( 'core/group' );

		$block_content = '<div class="wp-block-group"><p><a href="#">Link</a></p></div>';
		$block         = array(
			'blockName' => 'core/group',
			'attrs'     => array(
				'style' => array(
					'mobile' => array(
						'elements' => array(
							'link' => array(
								'color' => array(
									'text' => '#00ff00',
								),
							),
						),
					),
				),
			),
		);

		$actual = wp_render_block_states_support( $block_content, $block );

		$this->assertMatchesRegularExpression(
			'/^<div class="wp-block-group (wp-states-[a-f0-9]{8})"><p><a href="#">Link<\/a><\/p><\/div>$/',
			$actual
		);
		preg_match( '/wp-states-[a-f0-9]{8}/', $actual, $matches );
		$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

		$this->assertStringContainsString(
			'@media (width <= 480px){.' . $matches[0] . ' a:where(:not(.wp-element-button)){color:#00ff00 !important;}}',
			$actual_stylesheet
		);
	}

	/**
	 * Tests that a responsive pseudo-state generates media-query scoped CSS.
	 *
	 * @ticket 65239
	 */
	public function test_responsive_pseudo_state_generates_media_query_scoped_css() {
		$this->ensure_block_registered(
			'core/button',
			array(
				'root' => '.wp-block-button .wp-block-button__link',
			)
		);

		$block_content = '<div class="wp-block-button"><a class="wp-block-button__link">Click me</a></div>';
		$block         = array(
			'blockName' => 'core/button',
			'attrs'     => array(
				'style' => array(
					'mobile' => array(
						':hover' => array(
							'color' => array(
								'background' => '#ff00d0',
							),
						),
					),
				),
			),
		);

		$actual = wp_render_block_states_support( $block_content, $block );

		$this->assertMatchesRegularExpression(
			'/^<div class="wp-block-button (wp-states-[a-f0-9]{8})"><a class="wp-block-button__link">Click me<\/a><\/div>$/',
			$actual
		);
		preg_match( '/wp-states-[a-f0-9]{8}/', $actual, $matches );
		$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

		$this->assertStringContainsString(
			'@media (width <= 480px){.' . $matches[0] . ' .wp-block-button__link:hover{background-color:#ff00d0 !important;background-image:unset !important;}}',
			$actual_stylesheet
		);
	}

	/**
	 * Tests that a responsive block gap state generates layout spacing CSS.
	 *
	 * Responsive layout CSS is owned by wp_render_layout_support_flag()
	 * so it shares a selector with the base layout (the inner block wrapper for
	 * wrapper blocks) instead of being scoped to a separate `wp-states-...` class.
	 *
	 * @covers ::wp_render_layout_support_flag
	 *
	 * @ticket 65164
	 */
	public function test_responsive_block_gap_state_generates_layout_spacing_css() {
		$this->ensure_block_registered(
			'test/responsive-flow-layout-state',
			array(),
			array(
				'layout'  => array(
					'default' => array(
						'type' => 'default',
					),
				),
				'spacing' => array(
					'blockGap' => true,
				),
			)
		);

		add_theme_support( 'appearance-tools' );
		WP_Theme_JSON_Resolver::clean_cached_data();

		try {
			$block_content = '<div class="wp-block-test"><p>One</p><p>Two</p></div>';
			$block         = array(
				'blockName'    => 'test/responsive-flow-layout-state',
				'innerContent' => array( '<div class="wp-block-test">', null, '</div>' ),
				'attrs'        => array(
					'layout' => array(
						'type' => 'default',
					),
					'style'  => array(
						'mobile' => array(
							'spacing' => array(
								'blockGap' => '12px',
							),
						),
					),
				),
			);

			$actual = wp_render_layout_support_flag( $block_content, $block );
			preg_match( '/wp-container-test-responsive-flow-layout-state-is-layout-[a-f0-9]{8}/', $actual, $matches );
			$this->assertNotEmpty( $matches, "wp-container class missing in: $actual" );
			$container_class   = $matches[0];
			$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

			$this->assertStringContainsString(
				'@media (width <= 480px){.' . $container_class . ' > *{margin-block-start:0;margin-block-end:0;}}',
				$actual_stylesheet
			);
			$this->assertStringContainsString(
				'@media (width <= 480px){.' . $container_class . ' > * + *{margin-block-start:12px;margin-block-end:0;}}',
				$actual_stylesheet
			);
		} finally {
			remove_theme_support( 'appearance-tools' );
			WP_Theme_JSON_Resolver::clean_cached_data();
		}
	}

	/**
	 * Tests that responsive block gap state CSS uses the block's active layout type.
	 *
	 * @covers ::wp_render_layout_support_flag
	 *
	 * @ticket 65164
	 */
	public function test_responsive_block_gap_state_uses_active_layout_type() {
		$this->ensure_block_registered(
			'test/responsive-flex-layout-state',
			array(),
			array(
				'layout'  => array(
					'default' => array(
						'type' => 'flex',
					),
				),
				'spacing' => array(
					'blockGap' => true,
				),
			)
		);

		add_theme_support( 'appearance-tools' );
		WP_Theme_JSON_Resolver::clean_cached_data();

		try {
			$block_content = '<div class="wp-block-test"><p>One</p><p>Two</p></div>';
			$block         = array(
				'blockName'    => 'test/responsive-flex-layout-state',
				'innerContent' => array( '<div class="wp-block-test">', null, '</div>' ),
				'attrs'        => array(
					'layout' => array(
						'type' => 'flex',
					),
					'style'  => array(
						'mobile' => array(
							'spacing' => array(
								'blockGap' => '12px',
							),
						),
					),
				),
			);

			$actual = wp_render_layout_support_flag( $block_content, $block );
			preg_match( '/wp-container-test-responsive-flex-layout-state-is-layout-[a-f0-9]{8}/', $actual, $matches );
			$this->assertNotEmpty( $matches, "wp-container class missing in: $actual" );
			$container_class   = $matches[0];
			$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

			$this->assertStringContainsString(
				'@media (width <= 480px){.' . $container_class . '{gap:12px;}}',
				$actual_stylesheet
			);
		} finally {
			remove_theme_support( 'appearance-tools' );
			WP_Theme_JSON_Resolver::clean_cached_data();
		}
	}

	/**
	 * Tests that responsive layout state CSS can override grid layout values.
	 *
	 * @covers ::wp_render_layout_support_flag
	 *
	 * @ticket 65164
	 */
	public function test_responsive_layout_state_generates_grid_layout_css() {
		$this->ensure_block_registered(
			'test/responsive-grid-layout-state',
			array(),
			array(
				'layout' => array(
					'default' => array(
						'type' => 'grid',
					),
				),
			)
		);

		$block_content = '<div class="wp-block-test"><p>One</p><p>Two</p></div>';
		$block         = array(
			'blockName'    => 'test/responsive-grid-layout-state',
			'innerContent' => array( '<div class="wp-block-test">', null, '</div>' ),
			'attrs'        => array(
				'layout' => array(
					'type' => 'grid',
				),
				'style'  => array(
					'mobile' => array(
						'layout' => array(
							'minimumColumnWidth' => '8rem',
						),
					),
				),
			),
		);

		$actual = wp_render_layout_support_flag( $block_content, $block );
		preg_match( '/wp-container-test-responsive-grid-layout-state-is-layout-[a-f0-9]{8}/', $actual, $matches );
		$this->assertNotEmpty( $matches, "wp-container class missing in: $actual" );
		$container_class   = $matches[0];
		$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

		$this->assertStringContainsString(
			'@media (width <= 480px){.' . $container_class . '{grid-template-columns:repeat(auto-fill, minmax(min(8rem, 100%), 1fr));}}',
			$actual_stylesheet
		);
	}

	/**
	 * Tests that responsive layout state CSS can override grid columns.
	 *
	 * @covers ::wp_render_layout_support_flag
	 *
	 * @ticket 65164
	 */
	public function test_responsive_layout_state_generates_grid_column_count_css() {
		$this->ensure_block_registered(
			'test/responsive-grid-column-layout-state',
			array(),
			array(
				'layout' => array(
					'default' => array(
						'type' => 'grid',
					),
				),
			)
		);

		$block_content = '<div class="wp-block-test"><p>One</p><p>Two</p></div>';
		$block         = array(
			'blockName'    => 'test/responsive-grid-column-layout-state',
			'innerContent' => array( '<div class="wp-block-test">', null, '</div>' ),
			'attrs'        => array(
				'layout' => array(
					'type' => 'grid',
				),
				'style'  => array(
					'mobile' => array(
						'layout' => array(
							'columnCount' => 3,
						),
					),
				),
			),
		);

		$actual = wp_render_layout_support_flag( $block_content, $block );
		preg_match( '/wp-container-test-responsive-grid-column-layout-state-is-layout-[a-f0-9]{8}/', $actual, $matches );
		$this->assertNotEmpty( $matches, "wp-container class missing in: $actual" );
		$container_class   = $matches[0];
		$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

		$this->assertStringContainsString(
			'@media (width <= 480px){.' . $container_class . '{grid-template-columns:repeat(3, minmax(0, 1fr));}}',
			$actual_stylesheet
		);
	}

	/**
	 * Tests that different responsive layout states generate different container
	 * classes, even when the base layout configuration is identical.
	 *
	 * @covers ::wp_render_layout_support_flag
	 *
	 * @ticket 65164
	 */
	public function test_responsive_layout_state_generates_distinct_container_classes_for_distinct_viewport_styles() {
		$this->ensure_block_registered(
			'test/responsive-grid-distinct-layout-state',
			array(),
			array(
				'layout' => array(
					'default' => array(
						'type' => 'grid',
					),
				),
			)
		);

		$block_content = '<div class="wp-block-test"><p>One</p><p>Two</p></div>';
		$base_block    = array(
			'blockName'    => 'test/responsive-grid-distinct-layout-state',
			'innerContent' => array( '<div class="wp-block-test">', null, '</div>' ),
			'attrs'        => array(
				'layout' => array(
					'type' => 'grid',
				),
			),
		);
		$first_block   = array_replace_recursive(
			$base_block,
			array(
				'attrs' => array(
					'style' => array(
						'mobile' => array(
							'layout' => array(
								'columnCount' => 3,
							),
						),
					),
				),
			)
		);
		$second_block  = array_replace_recursive(
			$base_block,
			array(
				'attrs' => array(
					'style' => array(
						'mobile' => array(
							'layout' => array(
								'columnCount' => 4,
							),
						),
					),
				),
			)
		);

		$first_actual  = wp_render_layout_support_flag( $block_content, $first_block );
		$second_actual = wp_render_layout_support_flag( $block_content, $second_block );

		preg_match( '/wp-container-test-responsive-grid-distinct-layout-state-is-layout-[a-f0-9]{8}/', $first_actual, $first_matches );
		preg_match( '/wp-container-test-responsive-grid-distinct-layout-state-is-layout-[a-f0-9]{8}/', $second_actual, $second_matches );

		$this->assertNotEmpty( $first_matches, "wp-container class missing in: $first_actual" );
		$this->assertNotEmpty( $second_matches, "wp-container class missing in: $second_actual" );

		$first_container_class  = $first_matches[0];
		$second_container_class = $second_matches[0];

		$this->assertNotSame( $first_container_class, $second_container_class );

		$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

		$this->assertStringContainsString(
			'@media (width <= 480px){.' . $first_container_class . '{grid-template-columns:repeat(3, minmax(0, 1fr));}}',
			$actual_stylesheet
		);
		$this->assertStringContainsString(
			'@media (width <= 480px){.' . $second_container_class . '{grid-template-columns:repeat(4, minmax(0, 1fr));}}',
			$actual_stylesheet
		);
	}

	/**
	 * Tests that responsive grid layout and block gap state CSS are both generated.
	 *
	 * @covers ::wp_render_layout_support_flag
	 *
	 * @ticket 65164
	 */
	public function test_responsive_layout_state_generates_grid_columns_and_gap_css() {
		$this->ensure_block_registered(
			'test/responsive-grid-columns-gap-layout-state',
			array(),
			array(
				'layout'  => array(
					'default' => array(
						'type' => 'grid',
					),
				),
				'spacing' => array(
					'blockGap' => true,
				),
			)
		);

		add_theme_support( 'appearance-tools' );
		WP_Theme_JSON_Resolver::clean_cached_data();

		try {
			$block_content = '<div class="wp-block-test"><p>One</p><p>Two</p></div>';
			$block         = array(
				'blockName'    => 'test/responsive-grid-columns-gap-layout-state',
				'innerContent' => array( '<div class="wp-block-test">', null, '</div>' ),
				'attrs'        => array(
					'layout' => array(
						'type' => 'grid',
					),
					'style'  => array(
						'mobile' => array(
							'layout'  => array(
								'columnCount' => 3,
							),
							'spacing' => array(
								'blockGap' => '12px',
							),
						),
					),
				),
			);

			$actual = wp_render_layout_support_flag( $block_content, $block );
			preg_match( '/wp-container-test-responsive-grid-columns-gap-layout-state-is-layout-[a-f0-9]{8}/', $actual, $matches );
			$this->assertNotEmpty( $matches, "wp-container class missing in: $actual" );
			$container_class   = $matches[0];
			$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

			$this->assertStringContainsString(
				'@media (width <= 480px){.' . $container_class . '{grid-template-columns:repeat(3, minmax(0, 1fr));gap:12px;}}',
				$actual_stylesheet
			);
		} finally {
			remove_theme_support( 'appearance-tools' );
			WP_Theme_JSON_Resolver::clean_cached_data();
		}
	}

	/**
	 * Tests that responsive grid block gap CSS does not repeat unchanged layout declarations.
	 *
	 * @covers ::wp_render_layout_support_flag
	 *
	 * @ticket 65164
	 */
	public function test_responsive_grid_block_gap_state_only_outputs_changed_layout_css() {
		$this->ensure_block_registered(
			'test/responsive-grid-gap-state',
			array(),
			array(
				'layout'  => array(
					'default' => array(
						'type'               => 'grid',
						'minimumColumnWidth' => '12rem',
					),
				),
				'spacing' => array(
					'blockGap' => true,
				),
			)
		);

		add_theme_support( 'appearance-tools' );
		WP_Theme_JSON_Resolver::clean_cached_data();

		try {
			$block_content = '<div class="wp-block-test"><p>One</p><p>Two</p></div>';
			$block         = array(
				'blockName'    => 'test/responsive-grid-gap-state',
				'innerContent' => array( '<div class="wp-block-test">', null, '</div>' ),
				'attrs'        => array(
					'layout' => array(
						'type'               => 'grid',
						'minimumColumnWidth' => '12rem',
					),
					'style'  => array(
						'tablet' => array(
							'spacing' => array(
								'blockGap' => '12px',
							),
						),
					),
				),
			);

			$actual = wp_render_layout_support_flag( $block_content, $block );
			preg_match( '/wp-container-test-responsive-grid-gap-state-is-layout-[a-f0-9]{8}/', $actual, $matches );
			$this->assertNotEmpty( $matches, "wp-container class missing in: $actual" );
			$container_class   = $matches[0];
			$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

			$this->assertStringContainsString(
				'@media (480px < width <= 782px){.' . $container_class . '{gap:12px;}}',
				$actual_stylesheet
			);
			$this->assertStringNotContainsString(
				'@media (480px < width <= 782px){.' . $container_class . '{grid-template-columns:',
				$actual_stylesheet
			);
			$this->assertStringNotContainsString(
				'@media (480px < width <= 782px){.' . $container_class . '{container-type:',
				$actual_stylesheet
			);
		} finally {
			remove_theme_support( 'appearance-tools' );
			WP_Theme_JSON_Resolver::clean_cached_data();
		}
	}

	/**
	 * Tests that responsive child layout state CSS is generated.
	 *
	 * @covers ::wp_render_layout_support_flag
	 *
	 * @ticket 65164
	 */
	public function test_responsive_child_layout_state_generates_grid_span_css() {
		$this->ensure_block_registered( 'test/responsive-child-layout-state' );

		$block_content = '<p>Some text.</p>';
		$block         = array(
			'blockName'    => 'test/responsive-child-layout-state',
			'innerContent' => array( '<p>Some text.</p>' ),
			'attrs'        => array(
				'style' => array(
					'mobile' => array(
						'layout' => array(
							'columnSpan' => '2',
						),
					),
				),
			),
			'parentLayout' => array(
				'type'        => 'grid',
				'columnCount' => 3,
			),
		);

		$actual = wp_render_layout_support_flag( $block_content, $block );
		preg_match( '/wp-container-content-[a-f0-9]{8}/', $actual, $matches );
		$this->assertNotEmpty( $matches, "wp-container-content class missing in: $actual" );
		$container_content_class = $matches[0];
		$actual_stylesheet       = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

		$this->assertStringContainsString(
			'@media (width <= 480px){.' . $container_content_class . '{grid-column:span 2;}}',
			$actual_stylesheet
		);
	}

	/**
	 * Tests that a wrapper block (markup with an inner content wrapper) receives
	 * responsive grid layout CSS scoped to the inner wrapper, not the outermost tag.
	 *
	 * Regression test for the bug where wp-states-... was added to the outer tag
	 * while the wp-container-... layout class lives on the inner wrapper, causing
	 * the responsive @media rule to apply to the wrong element.
	 *
	 * @covers ::wp_render_layout_support_flag
	 *
	 * @ticket 65164
	 */
	public function test_responsive_layout_state_targets_inner_wrapper_for_wrapper_blocks() {
		$this->ensure_block_registered(
			'test/responsive-wrapper-grid-state',
			array(),
			array(
				'layout' => array(
					'default' => array(
						'type' => 'grid',
					),
				),
			)
		);

		$block_content = '<div class="wp-block-wrapper"><div class="wp-block-wrapper__inner-container"><p>One</p></div></div>';
		$block         = array(
			'blockName'    => 'test/responsive-wrapper-grid-state',
			'innerContent' => array(
				'<div class="wp-block-wrapper"><div class="wp-block-wrapper__inner-container">',
				null,
				'</div></div>',
			),
			'attrs'        => array(
				'layout' => array(
					'type' => 'grid',
				),
				'style'  => array(
					'mobile' => array(
						'layout' => array(
							'columnCount' => 3,
						),
					),
				),
			),
		);

		$actual = wp_render_layout_support_flag( $block_content, $block );

		// The wp-container-...-is-layout-... class should land on the inner wrapper.
		$this->assertMatchesRegularExpression(
			'/<div class="wp-block-wrapper__inner-container [^"]*wp-container-test-responsive-wrapper-grid-state-is-layout-[a-f0-9]{8}/',
			$actual
		);

		preg_match( '/wp-container-test-responsive-wrapper-grid-state-is-layout-[a-f0-9]{8}/', $actual, $matches );
		$container_class   = $matches[0];
		$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

		// The responsive @media rule must target the same selector that lives on
		// the inner wrapper element.
		$this->assertStringContainsString(
			'@media (width <= 480px){.' . $container_class . '{grid-template-columns:repeat(3, minmax(0, 1fr));}}',
			$actual_stylesheet
		);
	}

	/**
	 * Tests that state declarations are marked important.
	 *
	 * @ticket 65239
	 */
	public function test_state_declarations_generate_important_css() {
		$this->ensure_block_registered( 'core/button' );

		$block_content = '<div class="wp-block-button"><a class="wp-block-button__link">Click me</a></div>';
		$block         = array(
			'blockName' => 'core/button',
			'attrs'     => array(
				'style' => array(
					':hover' => array(
						'border' => array(
							'radius' => '8px',
						),
					),
				),
			),
		);

		wp_render_block_states_support( $block_content, $block );
		$actual_stylesheet = wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) );

		$this->assertStringContainsString(
			'border-radius:8px !important;',
			$actual_stylesheet
		);
	}
}
